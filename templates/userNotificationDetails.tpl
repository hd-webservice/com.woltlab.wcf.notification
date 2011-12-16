<div class="userNotificationDetails">
	<header>
		<div class="row">
			<a href="{link controller='User' id=$response->userID}{/link}" title="{$response->getUserProfile()->username}" class="userAvatar">
				<img src="https://www.woltlab.com/forum/wcf/images/avatars/avatar-8615.png" alt="" />
			</a>
			<hgroup>
				<h1>{$username}</h1>
				<h2>{@$time|time}</h2>
			</hgroup>
		</div>
	</header>
	<section>
		{@$message}
	</section>
	{if $buttons|count}
		<nav>
			<ul class="small-buttons"><!-- ToDo: Class-name written wrong to prevent inheritance -->
				{foreach from=$buttons item=button}
					<li data-action="{$button['actionName']}" data-class-name="{$button['className']}" data-object-id="{@$button['objectID']}">{$button['label']}</li>
				{/foreach}
			</ul>
		</nav>
	{/if}
</div>
