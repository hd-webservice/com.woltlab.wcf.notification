{*
	YES! This IS invalid html, but since the CSS matches this crappy structure,
	I will continue to use it for testing. Feel free to clean up this messed up
	markup whenever you want. Just include the template variables (e.g. $username)
	and you will be fine.
								- Alexander, 2011-10-14
*}
<div class="userNotificationDetails">
	<hgroup>
		<div class="row">
			<div class="avatar">
				<img src="https://www.woltlab.com/forum/wcf/images/avatars/avatar-8615.png" alt="" />
			</div>
			<div>
				<h1>{$username}</h1>
				<h2>{@$time|time}</h2>
			</div>
		</div>
	</hgroup>
	<section>
		{@$message}
	</section>
	{if $buttons|count}
		<nav>
			<ul>
				{foreach from=$buttons item=button}
					<li data-action="{$button['action']}" data-className="{$className}" data-objectID="{@$button['objectID']}">{$button['label']}</li>
				{/foreach}
			</ul>
		</nav>
	{/if}
</div>
