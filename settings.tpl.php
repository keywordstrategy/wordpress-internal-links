<div class="wrap">
<h2 style="float:left;">Keyword Strategy</h2>

<h2 class="nav-tab-wrapper">
<a class="nav-tab nav-tab-active" href="<?= KWS_PLUGIN_URL ?>">Overview</a>
	<a class="nav-tab" href="<?= KWS_PLUGIN_URL . '&kws_action=inpage' ?>">Insert Keywords</a>
	<a class="nav-tab" href="<?= KWS_PLUGIN_URL . '&kws_action=related' ?>">Links Needed</a>
</h2>

<? if (! function_exists('get_admin_url')): ?>
<p style="color:red;">Your WordPress version is not supported. Please update.</p>
<? endif; ?>

<? if ($kws_options['username'] && !isset($_GET['kws_login_error'])): ?>
<p>Current login: <b><?= htmlspecialchars($kws_options['username']) ?></b> <input type="button" class="button" value="Change login" style="margin-top: 15px;" onclick="document.getElementById('kws-login-box').style.display='block';this.parentNode.style.display='none';" /></p>
<div style="display:none;" id="kws-login-box">
<? else: ?>
<div>
<? endif; ?>
<h3> Keyword Strategy login </h3>
<form method="post" action="">
	<? if (isset($_GET['kws_login_error'])): ?> <p style="color:red;"><?= htmlspecialchars($_GET['kws_login_error']) ?></p> <? endif; ?>
	<input type="hidden" name="kws_action" value="login" />
	<div class="form-wrap">
		<label for="kws-username">Username</label>
		<input id="kws-username" name="kws-username" type="text" value="<?= htmlspecialchars(isset($_GET['kws_username'])? $_GET['kws_username'] : $kws_options['username']) ?>" aria-required="true" size="40" />
		<label for="kws-password">Password</label>
		<input id="kws-password" name="kws-password" type="password" value="" aria-required="true" size="40" />
	</div>
	<p class="submit"><input type="submit" class="button" value="Save" /></p> 
</form>
</div>

<? if ($kws_options['username']): ?>
	<? if ($kws_options['update_error']): ?>
		<p style="color:red;">Keywords update error: <?= htmlspecialchars($kws_options['update_error']) ?></p>
	<? endif; ?>
<p>Last update: <?= $kws_options['last_update']? date('Y-m-d H:i', $kws_options['last_update']).", {$kws_options['total_keywords']} keywords" : 'Never'?> <span><input class="button" type="submit" value="Update now" onclick="window.location = window.location.href + '&kws_action=update_now'; this.parentNode.innerHTML = 'Updating... Please wait...'" /></span></p>
<p> Your keywords will update automatically every day.


<br /><br /><br /><br />

<h3>Options</h3>
<form action="" method="post">
	<input type="hidden" value="save_options" name="kws_action" />
	<table class="form-table">
		<tr valign="top"> 
			<th scope="row"><label for="kws_linker_enabled" style="white-space:nowrap;"> Enable the keyword-URL internal linking</label></th> 
			<td><input name="kws_linker_enabled" type="checkbox" id="kws_linker_enabled" value="1" <?= isset($kws_options['linker_enabled']) && !$kws_options['linker_enabled']? '': 'checked="checked"' ?> /> </td> 
		</tr>
		<tr valign="top"> 
			<th scope="row"><label for="kws_tracker_enabled" style="white-space:nowrap;"> Enable the Keyword Strategy javascript tracker</label></th> 
			<td><input name="kws_tracker_enabled" type="checkbox" id="kws_tracker_enabled" value="1" <?= isset($kws_options['tracker_enabled']) && !$kws_options['tracker_enabled']? '': 'checked="checked"' ?> /> </td> 
		</tr>
		<tr valign="top"> 
			<th scope="row"><label for="kws_header_links" style="white-space:nowrap;"> Allow links in H1-H6 tags</label></th> 
			<td><input name="kws_header_links" type="checkbox" id="kws_header_links" value="1" <?= ! $kws_options['header_links']? '': 'checked="checked"' ?> /> </td> 
		</tr>
		<tr valign="top"> 
			<th scope="row"><label for="kws_wait_days" style="white-space:nowrap;"> New articles shouldn't show links for how many days</label></th> 
			<td><input name="kws_wait_days" type="text" id="kws_wait_days" value="<?= $kws_options['wait_days'] ?>" class="small-text" /> default: 0</td> 
		</tr>
		<tr valign="top"> 
			<th scope="row"><label for="kws_exact_match" style="white-space:nowrap;"> Minimum monthly searches for imported keywords</label></th> 
			<td><input name="kws_exact_match" type="text" id="kws_exact_match" value="<?= $kws_options['exact_match'] ?>" class="small-text" /> default: 140</td> 
		</tr>
		<tr valign="top"> 
			<th scope="row"><label for="kws_links_article" style="white-space:nowrap;"> Maximum number of links to insert per article</label></th> 
			<td><input name="kws_links_article" type="text" id="kws_links_article" value="<?= isset($kws_options['links_article'])? $kws_options['links_article'] : 10 ?>" class="small-text" /> default: 10</td> 
		</tr>
		<tr valign="top"> 
			<th scope="row"><label style="white-space:nowrap;"> Priority of links</label></th> 
			<td>
				<label><input name="kws_links_priority"  type="radio" value="long"  <?= $kws_options['links_priority'] == 'traffic'? '' : 'checked="checked"' ?>	/> Longest Keywords</label>
				<label><input name="kws_links_priority" type="radio" value="traffic" <?= $kws_options['links_priority'] == 'traffic'? 'checked="checked"' : '' ?> /> Highest Traffic Keywords</label>
			</td> 
		</tr>
		<tr valign="top"> 
			<th scope="row"><label style="white-space:nowrap;" for="kws_keywords_limit"> Maximum number of keywords to link sitewide</label></th> 
			<td>
				<select name="kws_keywords_limit" id="kws_keywords_limit">
					<? foreach ($keywords_limits AS $limit): ?>
						<option value="<?= $limit ?>" <? if($limit == $kws_options['keywords_limit'] || (!isset($kws_options['keywords_limit']) && $limit == 1000)): ?>selected="selected"<? endif; ?> ><?= $limit ?></option>
					<? endforeach; ?>
				</select>
				<b>If you are expereincing any performance issues, try to lower this.</b> default: 1000
			</td> 
		</tr>
		<tr valign="top">
			<th scope="row"><label style="white-space:nowrap;" for="kws_banned_urls"> Disable plugin on these pages:</label></th>
			<td>
				<div style="float:left; margin-right: 5px;">
					<textarea name="kws_banned_urls" rows="4" cols="40" wrap="off" id="kws_banned_urls"><?= htmlspecialchars($kws_options['banned_urls']) ?></textarea>
				</div>
				<div>
					Examples:<br />
					<b>/gallery/</b> will match all pages with /gallery/ inside.<br />
					<b>http://www.example.com/341-article</b> will match single article.<br />
					<b>/site/*/content/</b> will match /site/nature/content/, /site/politics/content/, /site/tech/content, etc.
				</div>
			</td>
		</tr>
	</table>
	<p class="submit"> 
		<input type="submit" name="Submit" class="button-primary" value="Save Changes" /> 
	</p> 
</form>

<p>
	Tip: You can put some text inside <b><i>&lt;kwsignore&gt;&lt;/kwsignore&gt;</i></b> tags to avoid plugin inserting links inside.
</p>
<? endif; ?>

</div>
