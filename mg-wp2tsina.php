<?php
/*
Plugin Name: MG WP to t.sina.com
Plugin URI: http://www.bymg.com/wordpress-plugins/mg-wp2tsina
Description: 将博客信息推送到新浪微博上
Version: 1.1.0
Author: Mike Gaul
Author URI: http://www.bymg.com
*/

if(preg_match('#' . basename(__FILE__) . '#', $_SERVER['PHP_SELF'])) { die('You are not allowed to call this page directly.'); }

if(!defined('MG_WP2TSINA_ABSPATH')) define('MG_WP2TSINA_ABSPATH', ABSPATH.'/'.PLUGINDIR.'/mg-wp2tsina' );

if (!class_exists('Snoopy'))
    require_once(MG_WP2TSINA_ABSPATH.'/Snoopy.class.php');
if (!class_exists('twSina'))
    require_once(MG_WP2TSINA_ABSPATH.'/tsina.api.class.php');

class mgPlugin_WP2TSina {
    
    var $user_id = '';
    var $user_password = '';
    
    var $if_force_sync = false; // 判断是否强制更新
    
    var $tsina = '';
    
    var $options = array(); // 配置信息存放
    
    var $post = null; // 用来存储当前文章的信息
    
    function mgPlugin_WP2TSina()
    {
        $this->tsina = new twSina();
        
        $this->options = get_option('mg_wp2tsina');
        
        if (!empty($this->options['user_id']) && !empty($this->options['user_password'])) {
            $this->tsina->user($this->options['user_id'], $this->options['user_password']);
        }
        
        if (empty($this->options['logo'])) $this->options['logo'] = '1';
        
        if (empty($this->options['delete'])) $this->options['delete'] = 1; // 默认彻底删除文章时，同步删除对应的新浪微博信息
        
        if (is_admin()) {
            add_action('admin_init', array(&$this, 'admin_init'));
            add_action('publish_post', array(&$this, 'sync'), 0);
            add_action('admin_menu', array(&$this, 'add_options_page'));
            add_action('admin_menu', array(&$this, 'add_custom_box_sidebox'));
            add_action('admin_notices', array(&$this, 'admin_notices'));
            if ($this->options['delete'] == 1) {
                add_action('delete_post', array(&$this, '__delete_tsina'));
            }
        }elseif (defined('XMLRPC_REQUEST')) {
            add_action('xmlrpc_publish_post', array(&$this, 'sync'), 0);
        }elseif (defined('APP_REQUEST')) {
            add_action('app_publish_post', array(&$this, 'sync'), 0);
        }else {
            // 如果要在文章详细页中显示同步标识
            if ($this->options['show_message_in_post']) {
                add_filter('the_content', array(&$this, 'show_logo_in_synced_post'));
            }
        }
    }
    
    function admin_init()
    {
        register_setting('mg_wp2tsina_options', 'mg_wp2tsina', array($this, 'options_validate'));
    }
    
    // 同步
    function sync($pID)
    {
        // 使用这种方式读取，解决XMLRPC操作没有$_POST变量的问题
        $this->post = get_post($pID);
        
        if (!empty($_POST['mg_wp2tsina_doit'])) { // 如果勾选了同步选项
            // do nothing
        }elseif (strstr($this->post->post_content, '{wp2tsina}')) { // 或者在文章内包含了 {wp2tsina}
            // 去掉文章内容中的同步标识
            $this->post->post_content = str_replace('{wp2tsina}', '', $this->post->post_content);
            
            global $wpdb;
            $wpdb->query("
	            UPDATE $wpdb->posts SET post_content = REPLACE(post_content, '{wp2tsina}', '')
	            WHERE ID = {$pID}");
        }else {
            return false;
        }
        
        // 判断是否已经被同步
        // 如果已经同步过了，则删除就的再次同步新的
        $tsina_id = get_post_meta($pID, 'mg_wp2tsina_id', true);
        if ($tsina_id && $this->tsina->del($tsina_id)) {
            delete_post_meta($pID, 'mg_wp2tsina_id');
        }
        
        $text = $this->__get_tsina_content();
        
        // 获得标题图片
        if (function_exists('get_post_thumbnail_id') &&
            function_exists('wp_attachment_is_image') &&
            function_exists('get_attached_file')) {
            
            $aid = get_post_thumbnail_id($pID);
            if ($aid && wp_attachment_is_image($aid)) {
                $pic = get_attached_file($aid);
            }
        }
        
        // 如果没有标题图片，则获取文内图片
        if (empty($pic)) {
            $pic = $this->__get_post_thumb($pID);
        }
        
        // 提交
        if (!empty($pic) && $pic != wp_upload_dir()) {
            $res = $this->tsina->post($text, $pic);
        }else {
            $res = $this->tsina->post($text);
        }
        
        $current_user = $this->get_current_user();
        update_option('mg_wp2tsina_last_result_for_'.$current_user->ID, $this->tsina->last_result, false);
        
        if ($res) {
            update_post_meta($pID, 'mg_wp2tsina_id', $res); // 发布成功，则将id进行存储
            return true;
        }else {
            return false;
        }
    }
    
    // 删除指定post对应的新浪微博信息
    function __delete_tsina($pID)
    {
        if (intval($pID) > 0) {
            $tsina_id = get_post_meta($pID, 'mg_wp2tsina_id', true);
            return !!$tsina_id && $this->tsina->del($tsina_id);
        }else {
            return false;
        }
    }
    
    // 保存后台配置信息
    function options_validate($options)
    {
        if (empty($options['sina_app_key']) || empty($options['sina_app_password'])) {
            $options['sina_app_key'] = $this->app_key;
            $options['sina_app_password'] = $this->app_password;
        }else {
            $options['sina_app_key'] = preg_replace('/[^\d]+/', '', $options['sina_app_key']);
            $options['sina_app_password'] = preg_replace('/[^\da-zA-Z]+/', '', $options['sina_app_password']);
        }
        
        $options['user_id'] = preg_replace('/[^\d]+/', '', $options['user_id']);
        if (!preg_match('/^[a-zA-Z\d\.\-\?_]{6,16}$/', $options['user_password'])) {
            $options['user_password'] = '';
        }
        
        // 微博信息组成如果用户都不选，那么发布信息的时候会使用文章标题作为内容主体
        if ( empty($options['msg']['title']) &&
             empty($options['msg']['categories']) &&
             empty($options['msg']['excerpt']) &&
             empty($options['msg']['tags']) ) {
        
            $options['msg']['title'] = '1';
        }
        
        $options['show_message_in_post'] = intval($options['show_message_in_post']);
        
        if (empty($options['supportus']['link'])) {
            $options['supportus']['link'] = -1;
        }else {
            $options['supportus']['link'] = 1;
            $this->__link2me();
        }
        
        if (empty($options['supportus']['tsina'])) {
            $options['supportus']['tsina'] = -1;
        }else {
            $options['supportus']['tsina'] = 1;
            $this->__friend2me();
        }
        
        return $options;
    }
    
    // 将后台配置页面添加到后台
    function add_options_page()
    {
        add_options_page(
            '新浪微博同步设置',
            '新浪微博同步设置',
            'administrator',
            __FILE__,
            array($this, 'output_options_page')
        );
    }
    
    // 输出后台配置页面
    function output_options_page()
    {
        settings_fields('mg_wp2tsina');
        require_once(MG_WP2TSINA_ABSPATH.'/options.php');
    }
    
    function add_custom_box_sidebox()
    {
    	if (function_exists('add_meta_box')) {
    		add_meta_box('mg-wp2tsina-setting', '同步到新浪微博', array($this, 'output_custom_sidebox'), 'post', 'side', 'high');
    	}
    }
    
    // 输出侧栏
    function output_custom_sidebox()
    {
        if (empty($this->options['user_id']) || empty($this->options['user_password'])) {
    	    echo "<p style=\"color:red;\">错误：您没有设置新浪微博帐号和密码。</p>\n";
        }else {
    	    echo "<p><label><input type=\"checkbox\" name=\"mg_wp2tsina_doit\" value=\"1\" />保存时同步</label></p>\n";
        }
    }
    
    function show_logo_in_synced_post($content)
    {
        global $post;
        
        if (!is_single($post->ID)) return $content;
        
        if ($tsina_id = get_post_meta($post->ID, 'mg_wp2tsina_id', true)) {
            
            $this->post = $post;
        
            $pic = ''; // 分享用的图片地址
            
            // 获得标题图片
            if (function_exists('get_post_thumbnail_id') &&
                function_exists('wp_attachment_is_image') &&
                function_exists('get_attached_file')) {
                
                $aid = get_post_thumbnail_id($this->post->ID);
                if ($aid && wp_attachment_is_image($aid)) {
                    $pic = wp_get_attachment_url($aid);
                }
            }
            
            // 读取第一张完整路径的图片地址
            if (!$pic) {
                preg_match('/<img[^>]+?src=[\'"](http:\/\/.+?)[\'"]/', $content, $matches);
                if (!empty($matches[1])) {
                    $pic = $matches[1];
                }
            }
            
            $logo_url = WP_PLUGIN_URL.'/mg-wp2tsina/logo-'.$this->options['logo'].'.png';
            $tsina_user_home = 'http://t.sina.com.cn/'.$this->options['user_id'];
            $blogname = get_bloginfo('name');
            $text = $this->__get_tsina_content(true);
            
            // 标识开始部分
            $out = <<<HTML

<!-- mg-wp2tsina -->
<div id="mgWP2TSina_SyncMessage" class="mgWP2TSina_SyncMessage">

HTML;
            
            // 标识图标部分
            if ($this->options['logo'] > 0) {
                $out .= <<<HTML

        <div class="mgWP2TSina_logo"><a href="{$tsina_user_home}" target="_blank" rel="nofollow"><img src="{$logo_url}" /></a></div>

HTML;
            }
            
            // 标识剩余文本部分
            $out .= <<<HTML

    <div class="mgWP2TSina_message">本文已经同步到新浪微博，<a href="{$tsina_user_home}" target="_blank" rel="nofollow">点击这里</a>访问“{$blogname}”的官方微博。</div>
    <div class="mgWP2TSina_share"><a href="javascript:void((function(s,d,e,r,l,p,t,z,c){var%20f='http://v.t.sina.com.cn/share/share.php?appkey={$this->app_key}',u=z||d.location,p=['&url=',e(u),'&title=',e(t||d.title),'&source=',e(r),'&sourceUrl=',e(l),'&content=',c||'gb2312','&pic=',e(p||'')].join('');function%20a(){if(!window.open([f,p].join(''),'mb',['toolbar=0,status=0,resizable=1,width=440,height=430,left=',(s.width-440)/2,',top=',(s.height-430)/2].join('')))u.href=[f,p].join('');};if(/Firefox/.test(navigator.userAgent))setTimeout(a,0);else%20a();})(screen,document,encodeURIComponent,'','','{$pic}','{$text}','{$this->post->guid}','utf-8'));">分享至微博</a></div>
</div>
<!-- // mg-wp2tsina -->

HTML;

            $content .= $out;
        }
        
        return $content;
    }
    
    function admin_notices()
    {
        $current_user = $this->get_current_user();
        $last_result = get_option('mg_wp2tsina_last_result_for_'.$current_user->ID, false);
        
        if (empty($last_result)) return false;
    
        echo '<div class="updated"><p>';
        
        if (empty($last_result['error_code'])) {
            echo '保存文章信息的同时，同步新浪微博成功！';
        }else {
            echo "同步新浪微博信息失败：{$last_result['error']}(#{$last_result['error_code']})";
        }
        
        echo '</p></div>';
        
        delete_option('mg_wp2tsina_last_result_for_'.$current_user->ID, false);
    }
    
    /**
     * 获得当前登录用户
     *
     */
    function get_current_user()
    {
        global $current_user;
        if ($current_user) return $current_user;
        
        require_once(ABSPATH.WPINC.'/pluggable.php');
        $current_user = wp_get_current_user();
        return $current_user;
    }
    
    /**
     * 获得文内图片地址（只一张）
     *
     */
    function __get_post_thumb($pID)
    {
        // 读取第一张完整路径的图片地址
        preg_match('/<img[^>]+?src=[\'"](http:\/\/.+?)[\'"]/', $this->post->post_content, $matches);
        if (!empty($matches[1])) {
            return $matches[1];
        }else {
            return false;
        }
    }
    
    /**
     * 生成一个链接到我们的网站
     *
     */
    function __link2me()
    {
        $linkdata = array(
            'link_name' => 'MG Core Team',
            'link_url' => 'http://www.bymg.com',
            'link_target' => '_blank',
            'link_description' => '提供各种自行开发的中文Wordpress插件和模板',
            'link_rating' => 5,
            //'link_category' => array(1,2),
        );
        
        if (!get_bookmarks(array('search' => $linkdata['link_name']))) {
            wp_insert_link($linkdata);
        }
    }
    
    function __friend2me()
    {
        return $this->tsina->friendto(1763927591);
    }
    
    function __get_tsina_content($nolink=false)
    {
        $text = '';
        
        // 加入分类
        if (!empty($this->options['msg']['categories'])) {
            $categories = wp_get_post_categories($this->post->ID);
            
            if ($categories) {
                foreach ($categories as $cat_ID) {
                    if (intval($cat_ID) > 0) {
                        $text .= get_cat_name($cat_ID).',';
                    }
                }
                
                $text = substr($text, 0, -1).':';
            }
        }
        
        // 加入标题
        if (!empty($this->options['msg']['title'])) {
            $text .= stripslashes('《'.strip_tags($this->post->post_title).'》');
        }
        
        // 加入TAG
        if (!empty($this->options['msg']['tags'])) {
            $posttags = get_the_tags($post->ID); 
            if ($posttags) {
                $tag_text = '';
                
                foreach ($posttags as $tag) {
                    $temp = preg_replace('/[\.\/|]/', '#,#', $tag->name);
                    $tag_text .= "#{$temp}#,";
                }
            }
            
            if (!empty($tag_text)) {
                $text .= ' 关键词:'.substr($tag_text, 0, -1).' ';
            }
        }
        
        // 加入文章摘要
        if (!empty($this->options['msg']['excerpt'])) {
            if (!empty($this->post->post_excerpt)) {
                $excerpt = strip_tags($this->post->post_excerpt);
                $excerpt = preg_replace('/[\t\r\n ]/i', '', $excerpt);
                $text .= $excerpt;
            }elseif (!empty($this->post->post_content)) {
                $excerpt .= strip_tags($this->post->post_content);
                $excerpt = preg_replace('/[\t\r\n ]/i', '', $excerpt);
                $text .= $excerpt;
            }
        }
        
        $text = htmlspecialchars_decode($text); // 处理HTML的特殊字符
        
        // 获得短链接
        if (function_exists('wp_get_shortlink')) {
            $shortlink = wp_get_shortlink($this->post->ID);
        }else {
            $shortlink = get_bloginfo('wpurl')."/?p={$this->post->ID}";
        }
        
        //{{{ 对信息长度进行处理
        $shortlink_length = strlen($shortlink);
        $text_length = $this->tsina->msg_length($text);
        $drop_length = $shortlink_length + $text_length - 140 - 2; // -2是因为后面需要加入省略号
        if ($drop_length > 0) {
            $text = $this->tsina->substr($text, $text_length - $drop_length);
        }
        
        // 检查裁剪长度后，#是否是偶数，如果不是则去掉最后一个#到结尾的字符串
        $topic_flag_count = substr_count($text, '#');
        if ($topic_flag_count%2 !== 0) {
            $text = preg_replace('/(.*)#.*?$/', '$1', $text);
        }
        $text = preg_replace('/,$/', '', $text); // 处理掉最后一个逗号(如果有的话)
        //}}} 对信息长度进行处理
        
        // 如果存在文章内容或者摘要，那么被截断后加入省略号
        if (!empty($this->post->post_excerpt) || !empty($this->post->post_content)) {
            $text .= '...';
        }
        
        if (!$nolink) $text .= ' '.$shortlink;
        
        return $text;
    }
}

$mg_wp2tsina = new mgPlugin_WP2TSina();
?>
