/**
 * Notification system for WCF.
 * 
 * @author	Alexander Ebert
 * @copyright	2001-2011 WoltLab GmbH
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 */
WCF.Notification = {};

/**
 * Notification handler, delegates business logic.
 */
WCF.Notification.Handler = function() { this.init(); };
WCF.Notification.Handler.prototype = {
	/**
	 * overlay object
	 * @var	WCF.Notification.Overlay
	 */
	_overlay: null,
	
	/**
	 * Initializes notification handler.
	 */
	init: function() {
		$('#userNotifications').mouseover($.proxy(this._showOverlay, this));
	},
	
	/**
	 * Displays notification overlay.
	 */
	_showOverlay: function() {
		if (this._overlay === null) {
			this._overlay = new WCF.Notification.Overlay();
		}
		else if (!this._overlay.isOpen()) {
			this._overlay.show();
		}
	}
};

/**
 * Notification overlay, created at runtime.
 */
WCF.Notification.Overlay = function() { this.init() };
WCF.Notification.Overlay.prototype = {
	/**
	 * scrollable API
	 * @var	jquery.fn.scrollable
	 */
	_api: null,
	
	/**
	 * overlay container
	 * @var	jQuery
	 */
	_container: null,
	
	/**
	 * active notification id
	 * @var	integer
	 */
	_notificationID: 0,
	
	/**
	 * overlay state
	 * @var	boolean
	 */
	_isOpen: false,
	
	/**
	 * Creates a new overlay on init.
	 */
	init: function() {
		this._createOverlay();
	},
	
	/**
	 * Creates the notification overlay.
	 */
	_createOverlay: function() {
		$('<div class="userNotificationContainer"><div id="userNotificationContainer" class="scrollableContainer"><div class="scrollableItems"><div><span class="next">&lt;Spinner&gt;</span></div><div><span class="prev">Details</span></div></div></div></div>').appendTo('body');
		this._container = $('#userNotificationContainer');
		
		// initialize scrollable API
		this._container.scrollable({
			mousewheel: true,
			speed: 200
		});
		this._api = this._container.data('scrollable');
		
		// move container beneath notification badge
		var $offsets = $('#userNotifications').getOffsets();
		var $dimensions = $('#userNotifications').getDimensions('outer');
		
		// align overlay with lower left corner
		this._container.parent().css({
			left: $offsets.left + 'px',
			top: ($offsets.top + $dimensions.height) + 'px'
		});
		
		// load notifications
		this._loadContent();
	},
	
	/**
	 * Loads notifications.
	 */
	_loadContent: function() {
		new WCF.Notification.Loader(this._container, $.proxy(this._bindListener, this));
	},
	
	/**
	 * Binds click listener for all items.
	 * 
	 * @param	jQuery		notificationList
	 */
	_bindListener: function(notificationList) {
		notificationList.find('li').each($.proxy(function(index, item) {
			$(item).click($.proxy(this._showMessage, this));
		}, this));
	},
	
	/**
	 * Displays the message (output) for current item.
	 * 
	 * @param	object		event
	 */
	_showMessage: function(event) {
		var $item = $(event.target);
		
		// set notification id
		this._notificationID = $item.data('notificationID');
		
		// set fixed height (prevents box resize without animation)
		var $containerDimensions = this._container.getDimensions('outer');
		this._container.css({ height: $containerDimensions.height + 'px' });
		
		// insert html
		var $messageContainer = this._container.find('div.scrollableItems div:eq(1)');
		$messageContainer.html($item.data('message')).click($.proxy(this.showList, this));
		var $messageContainerDimensions = $messageContainer.getDimensions('outer');
		
		// bind buttons
		$messageContainer.find('nav li').each($.proxy(function(index, button) {
			var $button = $(button);
			$button.click($.proxy(function(event) {
				new WCF.Notification.Action(this, this._api, this._container, this._notificationID, $button);
				
				return false;
			}, this));
		}, this));
		
		// adjust height
		if ($containerDimensions.height != $messageContainerDimensions.height) {
			this._container.animate({
				height: $messageContainerDimensions.height
			}, 200);
		}
		
		// show message
		this._api.next();
	},
	
	/**
	 * Displays list of notification items.
	 */
	showList: function() {
		this._api.prev();
		
		var $listHeight = this._container.find('div.scrollableItems div:eq(0)').getDimensions();
		this._container.animate({
			height: $listHeight.height + 'px'
		}, 200);
	},
	
	/**
	 * Returns true if overlay is active and visible.
	 * 
	 * @return	boolean
	 */
	isOpen: function() {
		return this._isOpen;
	},
	
	/**
	 * Closes the overlay.
	 */
	close: function() {
		this._container.hide();
		this._isOpen = false;
	},
	
	/**
	 * Displays the overlay.
	 */
	show: function() {
		this._container.show();
		this._isOpen = true;
	}
};

/**
 * Action fired upon button clicks within message.
 * 
 * @param	WCF.Notification.Overlay	overlay
 * @param	jQuery.fn.scrollable		api
 * @param	jQuery				container
 * @param	integer				notificationID
 * @param	jQuery				targetElement
 */
WCF.Notification.Action = function(overlay, api, container, notificationID, targetElement) { this.init(overlay, api, container, notificationID, targetElement); };
WCF.Notification.Action.prototype = {
	/**
	 * scrollable API
	 * @var	jQuery.fn.scrollable
	 */
	_api: null,
	
	/**
	 * overlay container
	 * @var	jQuery
	 */
	_container: null,
	
	/**
	 * item list container
	 * @var	jQuery
	 */
	_itemContainer: null,
	
	/**
	 * loading overlay with spinner
	 * @var	jQuery
	 */
	_loading: null,
	
	/**
	 * current notification id
	 * @var	integer
	 */
	_notificationID: 0,
	
	/**
	 * notification overlay
	 * @var	WCF.Notification.Overlay
	 */
	_overlay: null,
	
	/**
	 * target element
	 * @var	jQuery
	 */
	_targetElement: null,
	
	/**
	 * Initializes a new action.
	 * 
	 * @param	WCF.Notification.Overlay	overlay
 	 * @param	jQuery.fn.scrollable		api
 	 * @param	jQuery				container
 	 * @param	integer				notificationID
 	 * @param	jQuery				targetElement
	 */
	init: function(overlay, api, container, notificationID, targetElement) {
		this._api = api;
		this._container = container;
		this._itemContainer = this._container.find('div.scrollableItems div:eq(0)');
		this._notificationID = notificationID;
		this._overlay = overlay;
		this._targetElement = targetElement;
		
		// send ajax request
		var $parameters = {};
		$parameters[this._targetElement.data('objectID')] = this._notificationID;
		
		new WCF.Action.Proxy({
			autoSend: true,
			data: {
				actionName: this._targetElement.data('action'),
				className: this._targetElement.data('className'),
				objectIDs: [ this._targetElement.data('objectID') ],
				parameters: {
					notificationID: $parameters
				}
			},
			init: $.proxy(this._showLoadingOverlay, this),
			success: $.proxy(this._hideLoadingOverlay, this)
		});
	},
	
	/**
	 * Removes an item from list. An empty list will result in a notice displayed to user.
	 */
	_removeItem: function() {
		this._itemContainer.find('li').each($.proxy(function(index, item) {
			var $item = $(item);
			if ($item.data('notificationID') == this._notificationID) {
				// remove item itself
				$item.remove();
				
				// remove complete list
				var $listItems = this._itemContainer.find('li');
				if (!$listItems.length) {
					this._itemContainer.html('<p>' + WCF.Language.get('wcf.user.notification.noNotifications') + '</p>');
				}
				
				// show list
				this._overlay.showList();
			}
		}, this));
	},
	
	/**
	 * Displays an overlay during loading.
	 */
	_showLoadingOverlay: function() {
		if (this._loading == null) {
			this._loading = $('<div id="userNotificationDetailsLoading"></div>').appendTo($('body')[0]);
			
			var $parentContainer = this._container.parent();
			var $dimensions = $parentContainer.getDimensions('outer');
			this._loading.css({
				height: $dimensions.height + 'px',
				left: $parentContainer.css('left'),
				top: $parentContainer.css('top'),
				width: $dimensions.width + 'px'
			});
		}
		
		this._loading.show();
	},
	
	/**
	 * Hides overlay after successful execution.
	 */
	_hideLoadingOverlay: function(data, textStatus, jqXHR) {
		this._loading.hide();
		this._removeItem();
	}
};

/**
 * Loads notifications.
 * 
 * @param	jQuery		container
 * @param	function	callback
 */
WCF.Notification.Loader = function(container, callback) { this.init(container, callback); };
WCF.Notification.Loader.prototype = {
	/**
	 * callback once all items are loaded
	 * @var	function
	 */
	_callback: null,
	
	/**
	 * overlay container
	 * @var	jQuery
	 */
	_container: null,
	
	/**
	 * Loads notifications.
	 * 
	 * @param	jQuery		container
	 * @param	function	callback
	 */
	init: function(container, callback) {
		this._container = container;
		this._callback = callback;
		
		// send ajax request
		new WCF.Action.Proxy({
			autoSend: true,
			data: {
				actionName: 'load',
				className: 'wcf\\data\\user\\notification\\UserNotificationAction'
			},
			success: $.proxy(this._success, this)
		});
	},
	
	/**
	 * Insert items after successful ajax query.
	 * 
	 * @param	object		data
	 * @param	string		textStatus
	 * @param	jQuery		jqXHR
	 */
	_success: function(data, textStatus, jqXHR) {
		$('#userNotifications').text(eval(WCF.Language.get('wcf.user.notification.count')));
		
		if (!data.returnValues.count) {
			this._container.find('div.scrollableItems div:eq(0)').html('<p>' + WCF.Language.get('wcf.user.notification.noNotifications') + '</p>');
			
			return;
		}
		
		// create list container
		this._container.find('div.scrollableItems div:eq(0)').html('<ul></ul>');
		var $notificationList = this._container.find('div.scrollableItems ul');
		
		// insert notification items
		for (var i in data.returnValues.notifications) {
			var $notification = data.returnValues.notifications[i];
			
			var $item = $('<li>' + $notification.label + '</li>').data('notificationID', $notification.notificationID).data('message', $notification.message);
			$item.appendTo($notificationList);
		}
		
		// execute callback
		this._callback($notificationList);
	}
};