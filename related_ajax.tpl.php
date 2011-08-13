<? if ($urls): ?>
<p>
			<? $links_needed = ($kws_options['related_links']? $kws_options['related_links'] : 1) - $item['links'] ?>
Insert the keyword '<?= htmlspecialchars($keyword) ?>' into <?= $links_needed < 0 ? 0 : $links_needed ?> of these suggested pages. We'll autolink them together to create an internal link.
</p>
	<? foreach ($urls AS  $item): ?>
		<? extract($item); ?>
	<p>
		<a href="<?= htmlspecialchars($url) ?>" target="_blank"><?= htmlspecialchars($url) ?></a>,
		<?= $url_links ?> links
		<br />
		<a target="_blank" href="post.php?post=<?= $post_id ?>&amp;action=edit" title="Edit this page">Edit&nbsp;Page</a>
		<a class="thickbox" href="<?= admin_url('admin-ajax.php') ?>?action=kws_related_urls&amp;kws_keyword=<?= (urlencode($keyword)) ?>&amp;not_appropriate=<?= urlencode($url) ?>">Not&nbsp;Appropriate</a>
	</p>
	<? endforeach; ?>
<? else: ?>
	<div style="text-align: center; margin-top:20px;">
		<p>Unfortunately, we weren't able to find any other pages on your site to use for links.</p> 		<p>Create more content and we'll try again later.</p>
	</div>
<? endif; ?>
