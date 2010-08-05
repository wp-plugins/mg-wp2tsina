<div class="wrap">
    <h2>新浪微博同步设置</h2>
    
    <form method="post" action="options.php">
    <?php wp_nonce_field('update-options'); ?>
    
    <p><input type="checkbox" name="mg_wp2tsina[supportus][tsina]" value="1"<?php echo $this->options['supportus']['tsina']!=-1 ? ' checked' : ''; ?> />建议：关注我们的<a href="http://t.sina.com.cn/bymgcom" target="_blank">官方新浪微博</a>，获得最新插件更新信息。</p>
    
    <?php if (0): ?>
    <h3>新浪微博接口</h3>
    <p>接口方式需要提供申请新浪微博API。如果没有，则本插件会使用默认的API信息（此API信息不保证永久可用，如果失效请自行申请）。</p>
    
    <table class="form-table">
        <tr valign="top">
            <th scope="row">API KEY</th>
            <td>
                <input type="text" size="20" name="mg_wp2tsina[sina_app_key]" value="<?php echo $this->options['sina_app_key']; ?>" />
                <br/>
                <span class="description">新浪微博接口的API KEY，例如：42499****</span>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">API Password</th>
            <td>
                <input type="text" size="32" name="mg_wp2tsina[sina_app_password]" value="<?php echo $this->options['sina_app_password']; ?>" />
                <br/>
                <span class="description">新浪微博接口的API Password，例如：97fb7350dcb36f1703607d5*********</span>
            </td>
        </tr>
    </table>
    
    <p class="submit">
        <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
    </p>
    <?php endif; ?>
    
    <h3>新浪微博帐号</h3>
    <p>本部分信息需要您填写新浪微博的登录帐号信息。</p>
    
    <table class="form-table">
        <tr valign="top">
            <th scope="row">用户UID（纯数字格式）</th>
            <td>
                <input type="text" size="20" name="mg_wp2tsina[user_id]" value="<?php echo $this->options['user_id']; ?>" />
                <br/>
                <span class="description">获得方法：登录新浪微博，点击用户头像（右侧）下方“关注”链接，在地址栏中可以看到类似 http://t.sina.com.cn/1723723610/follow 这样的链接，其中1723723610就是需要输入的内容。</span>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">密码</th>
            <td>
                <input type="password" size="32" name="mg_wp2tsina[user_password]" value="<?php echo $this->options['user_password']; ?>" />
                <br/>
                <span class="description">登录新浪微博时使用的密码</span>
            </td>
        </tr>
    </table>
    
    <p class="submit">
        <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
    </p>
    
    <h3>同步操作设置</h3>
    <p>本部分将帮助设定同步到新浪微博上的信息的组成内容，以及其它和同步相关的功能。</p>
    
    <table class="form-table">
        <tr valign="top">
            <th scope="row">新浪微博将显示</th>
            <td>
                <p>
                <label><input type="checkbox" name="mg_wp2tsina[msg][title]" value="1"<?php echo !empty($this->options['msg']['title']) ? ' checked' : ''; ?> />标题</label>
                &nbsp;&nbsp;
                <label><input type="checkbox" name="mg_wp2tsina[msg][categories]" value="1"<?php echo !empty($this->options['msg']['categories']) ? ' checked' : ''; ?> />分类</label>
                &nbsp;&nbsp;
                <label><input type="checkbox" name="mg_wp2tsina[msg][excerpt]" value="1"<?php echo !empty($this->options['msg']['excerpt']) ? ' checked' : ''; ?> />内容摘要</label>
                &nbsp;&nbsp;
                <label><input type="checkbox" name="mg_wp2tsina[msg][tags]" value="1"<?php echo !empty($this->options['msg']['tags']) ? ' checked' : ''; ?> />标签</label>
                &nbsp;&nbsp;
                链接（必须）
                </p>
                <span class="description">上述所有内容如果都不选择，那么发布信息的时候会使用文章标题作为内容主体。</span>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">是否删除微博信息？</th>
            <td>

<?php
$valid_options = array(
    1 => '彻底删除文章时删除',
    2 => '不删除',
);

foreach ($valid_options as $key => $val) {
	if ($key == $this->options['delete']) {
		echo '<label><input type="radio" name="mg_wp2tsina[delete]" value="'.$key.'" checked="true" />'.$val.'&nbsp;&nbsp;</label>';
	}else {
		echo '<label><input type="radio" name="mg_wp2tsina[delete]" value="'.$key.'" />'.$val.'&nbsp;&nbsp;</label>';
	}
}
?>
            </td>
        </tr>
    </table>
    
    <p class="submit">
        <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
    </p>
    
    <h3>文章页显示设置</h3>
    
    <table class="form-table">
        <tr valign="top">
            <th scope="row">是否在Post详细页显示已经同步？</th>
            <td>

<?php
$valid_options = array(
    1 => '显示',
    0 => '不显示',
);

foreach ($valid_options as $key => $val) {
	if ($key == $this->options['show_message_in_post']) {
		echo '<label><input type="radio" name="mg_wp2tsina[show_message_in_post]" value="'.$key.'" checked="true" />'.$val.'&nbsp;&nbsp;</label>';
	}else {
		echo '<label><input type="radio" name="mg_wp2tsina[show_message_in_post]" value="'.$key.'" />'.$val.'&nbsp;&nbsp;</label>';
	}
}
?>
                <br/>
                <span class="description">在文章的详细页，如果本篇文章已经被同步到新浪微博上，那么在文章正文内容下方显示一个提示信息。</span>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">显示图标</th>
            <td>
                <label><input type="radio" name="mg_wp2tsina[logo]" value="-1"<?php echo !empty($this->options['logo']) && $this->options['logo']==-1 ? ' checked' : ''; ?> />不显示微博图标</label>
                &nbsp;&nbsp;
                <label><input type="radio" name="mg_wp2tsina[logo]" value="1"<?php echo !empty($this->options['logo']) && $this->options['logo']==1 ? ' checked' : ''; ?> /><img src="<?php echo WP_PLUGIN_URL; ?>/mg-wp2tsina/logo-1.png" border="0" /></label>
                &nbsp;&nbsp;
                <label><input type="radio" name="mg_wp2tsina[logo]" value="2"<?php echo !empty($this->options['logo']) && $this->options['logo']==2 ? ' checked' : ''; ?> /><img src="<?php echo WP_PLUGIN_URL; ?>/mg-wp2tsina/logo-2.png" border="0" /></label>
                &nbsp;&nbsp;
                <label><input type="radio" name="mg_wp2tsina[logo]" value="3"<?php echo !empty($this->options['logo']) && $this->options['logo']==3 ? ' checked' : ''; ?> /><img src="<?php echo WP_PLUGIN_URL; ?>/mg-wp2tsina/logo-3.png" border="0" /></label>
                &nbsp;&nbsp;
                <label><input type="radio" name="mg_wp2tsina[logo]" value="4"<?php echo !empty($this->options['logo']) && $this->options['logo']==4 ? ' checked' : ''; ?> /><img src="<?php echo WP_PLUGIN_URL; ?>/mg-wp2tsina/logo-4.png" border="0" /></label>
                &nbsp;&nbsp;
                <label><input type="radio" name="mg_wp2tsina[logo]" value="5"<?php echo !empty($this->options['logo']) && $this->options['logo']==5 ? ' checked' : ''; ?> /><img src="<?php echo WP_PLUGIN_URL; ?>/mg-wp2tsina/logo-5.png" border="0" /></label>
            </td>
        </tr>
    </table>
    
    <p class="submit">
        <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
    </p>
    
    <h3>支持我们</h3>
    <p>如果您觉得插件好用，我们希望您能够通过以下方式支持我们继续努力。由此给您带来的困惑和不便我们深表歉意。</p>
    
    <table class="form-table">
        <tr valign="top">
            <td>
                <label><input type="checkbox" name="mg_wp2tsina[supportus][link]" value="1"<?php echo $this->options['supportus']['link']!=-1 ? ' checked' : ''; ?> />添加链接到我们网站</label>
            </td>
        </tr>
    </table>
    
    <input type="hidden" name="action" value="update" />
    <input type="hidden" name="page_options" value="mg_wp2tsina" />
    
    <p class="submit">
        <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
    </p>
    
    </form>
</div>
