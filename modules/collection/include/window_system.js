/**
 * Window Management System
 *
 * Copyright (c) 2013. by Way2CU
 * Author: Mladen Mijatov
 *
 * This window management system was designed to be used with Caracal framework
 * administration system and has very little use outside of it. Is you are going
 * to take parts from this file leave credits intact.
 *
 * Requires jQuery 1.4.2+
 */

var window_system = null;

/**
 * Dialog Constructor
 */
function Dialog() {
	var self = this;

	self.cover = $('<div>');
	self.dialog = $('<div>');
	self.title = $('<div>');
	self.title_bar = $('<div>');
	self.close_button = $('<a>');
	self.container = $('<div>');

	/**
	 * Finish object initialization
	 */
	self.init = function() {
		self.cover
				.css('display', 'none')
				.addClass('dialog')
				.append(self.dialog)
				.appendTo($('body'));

		self.dialog
				.addClass('container')
				.append(self.title_bar)
				.append(self.container);

		self.title_bar
				.addClass('title_bar')
				.append(self.close_button)
				.append(self.title);

		self.title.addClass('title');
		self.container.addClass('content');

		self.close_button
				.addClass('close_button')
				.click(self.hide);
	};

	/**
	 * Show dialog
	 */
	self.show = function() {
		self.dialog.css('opacity', 0);

		self.cover
				.css({
					display: 'block',
					opacity: 0
				})
				.animate(
					{opacity: 1}, 300,
					function() {
						self.adjustPosition();
						self.dialog
								.delay(200)
								.animate({opacity: 1}, 300);
					}
				);
	};

	/**
	 * Hide dialog
	 */
	self.hide = function() {
		self.cover
				.css('opacity', 1)
				.animate(
					{opacity: 0}, 300,
					function() {
						self.cover.css('display', 'none');
						self.container.html('');
					}
				);
	};

	/**
	 * Set dialog content and make proper animation
	 *
	 * @param mixed content
	 * @param integer width
	 * @param integer height
	 */
	self.setContent = function(content, width, height) {
		var start_params = {
				top: Math.round($(document).height() / 2),
				left: Math.round($(document).width() / 2),
				width: 0
			};

		var c_start_params = {
				height: 0
			};

		var end_params = {
				top: Math.round(($(document).height() - height) / 2),
				left: Math.round(($(document).width() - width) / 2),
				width: width
			};

		var c_end_params = {
				height: height
			};

		// assign content
		self.container.css(c_start_params);

		self.dialog
				.css(start_params)
				.animate(
					end_params, 500,
					function() {
						self.container.animate(
								c_end_params, 500,
								function() {
									self.container.html(content);
								}
							);
					}
				);
	};

	/**
	 * Set dialog title
	 *
	 * @param string title
	 */
	self.setTitle = function(title) {
		self.title.html(title);
	};

	/**
	 * Set dialog in loading state
	 */
	self.setLoadingState = function() {
		self.dialog.addClass('loading');
	};

	/**
	 * Set dialog in normal state
	 */
	self.setNormalState = function() {
		self.dialog.removeClass('loading');
	};

	/**
	 * Adjust position of inner container
	 */
	self.adjustPosition = function() {
		self.dialog.css({
						top: Math.round(($(document).height() - self.dialog.height()) / 2),
						left: Math.round(($(document).width() - self.dialog.width()) / 2)
					});

	};

	// finish object initialization
	self.init();
}

/**
 * Window Constructor
 *
 * @param string id
 * @param integer width
 * @param string title
 * @param boolean can_close
 * @param string url
 * @param boolean existing_structure Allow creating window from existing structure
 */
function Window(id, width, title, can_close, url, existing_structure) {
	var self = this;

	self.id = id;
	self.url = url;
	self.visible = false;
	self.zIndex = 1000;

	self.title = null;
	self.container = null;
	self.content = null;
	self.close_button = null;
	self.main_menu = null;
	self.window_list_item = $('<li>');

	self.parent = null;
	self.window_system = null;

	/**
	 * Finish object initialization.
	 */
	self.init = function() {
		if (!existing_structure) {
			// create new window structure
			self.container = $('<div>');
			self.container
					.attr('id', self.id)
					.addClass('window')
					.css('width', width)
					.click(self._handleClick)
					.hide();

			self.title = $('<div>')
			self.title
					.addClass('title')
					.html(title)
					.drag(self._handleDrag)
					.appendTo(self.container);

			self.content = $('<div>')
			self.content
					.addClass('content')
					.appendTo(self.container);

		} else {
			// inherit existing structure and configure it
			self.container = $('#' + id);
			self.title = self.container.children('div.title').eq(0);
			self.content = self.container.children('div.content').eq(0);
		}

		// add close button if needed
		if (can_close) {
			self.close_button = $('<a>')
			self.close_button
					.addClass('close_button')
					.click(self.close)
					.appendTo(self.title);
		}

	};

	/**
	 * Handle clicking anywhere on window.
	 *
	 * @param object event
	 */
	self._handleClick = function(event) {
		self.window_system.focusWindow(self.id);
	};
	
	/**
	 * Handle clicking on window list.
	 *
	 * @param object event
	 */
	self._handleWindowListClick = function(event) {
		event.preventDefault();
		self.window_system.focusWindow(self.id);
	};

	/**
	 * Handle dragging window.
	 *
	 * @param object event
	 * @param object position
	 */
	self._handleDrag = function(event, position) {
		event.preventDefault();

		self.container.css({
					top: position.offsetY,
					left: position.offsetX
				});
	};

	/**
	 * Show window.
	 *
	 * @param boolean center
	 */
	self.show = function(center) {
		if (self.visible) return self;  // don't allow animation of visible window

		var params = {
			display: 'block',
			opacity: 0
		};

		// center if needed
		if (center != undefined && center) {
			params.top = Math.floor((self.parent.height() - self.container.height()) / 2) - 50;
			params.left = Math.floor((self.parent.width() - self.container.width()) / 2);

			if (params.top < 35)
				params.top = 35;
		}

		// apply params and show window
		self.container
			.css(params)
			.animate({opacity: 1}, 300);

		self.visible = true;

		// set window to be top level
		self.window_system.focusWindow(self.id);

		return self;
	};

	/**
	 * Close window.
	 */
	self.close = function() {
		self.container.animate(
				{opacity: 0}, 300, 
				function() {
					self.visible = false;
					self.window_system.removeWindow(self);
					self.window_system.focusTopWindow();
				});

		self.window_list_item.remove();
	};

	/**
	 * Set focus on self.
	 */
	self.focus = function() {
		self.window_system.focusWindow(self.id);
		return self;
	};

	/**
	 * Attach window to specified container.
	 *
	 * @param object system
	 */
	self.attach = function(system) {
		// attach container to main container
		system.container.append(self.container);

		// save parent for later use
		self.parent = system.container;
		self.window_system = system;

		// add window list item
		self.window_list_item
				.html(self.title.html())
				.appendTo(self.window_system.window_list)
				.click(self._handleWindowListClick);

		return self;
	};

	/**
	 * Event triggered when window gains focus.
	 */
	self.gainFocus = function() {
		// set level
		self.zIndex = 1000;
		self.container
				.css({zIndex: self.zIndex})
				.animate({opacity: 1}, 300)
				.addClass('focused');

		// change window list item
		self.window_list_item.addClass('active');
	};

	/**
	 * Event triggered when window looses focus.
	 */
	self.loseFocus = function() {
		self.zIndex--;
		self.container
				.css({zIndex: self.zIndex})
				.animate({opacity: 0.5}, 300)
				.removeClass('focused');

		// change window list item
		self.window_list_item.removeClass('active');
	};

	/**
	 * Load/reload window content.
	 *
	 * @param string url
	 */
	self.loadContent = function(url) {
		if (self.url == null)
			return;

		if (url != undefined)
			self.url = url;

		self.container.addClass('loading');

		$.ajax({
			cache: false,
			context: self,
			dataType: 'html',
			success: self.contentLoaded,
			error: self.contentError,
			url: self.url
		});

		return self;  // allow linking
	};

	/**
	 * Submit form content to server and load response.
	 *
	 * @param object form
	 */
	self.submitForm = function(form) {
		self.container.addClass('loading');

		var data = {};
		var field_types = ['input', 'select', 'textarea'];

		// collect data from from
		for(var index in field_types) {
			var type = field_types[index];

			$(form).find(type).each(function() {
				if ($(this).hasClass('multi-language')) {
					// multi-language input field, we need to gather other data
					var name = $(this).attr('name');
					var temp_data = $(this).data('language');

					for (var language in temp_data)
						data[name + '_' + language] = temp_data[language];

				} else {
					if ($(this).attr('type') == 'checkbox') {
						// checkbox
						data[$(this).attr('name')] = this.checked ? 1 : 0;

					} else if ($(this).attr('type') == 'radio') {
						// radio button
						var group_name = $(this).attr('name');

						if (data[group_name] == undefined) 
							data[group_name] = $(form).find('input:radio[name='+group_name+']:checked').val();

					} else {
						// all other components
						data[$(this).attr('name')] = $(this).val(); 
					}
				}
			});
		};

		// send data to server
		$.ajax({
			cache: false,
			context: self,
			dataType: 'html',
			type: 'POST',
			data: data,
			success: self.contentLoaded,
			error: self.contentError,
			url: $(form).attr('action')
		});
	};

	/**
	 * Event fired on when window content has finished loading.
	 *
	 * @param string data
	 */
	self.contentLoaded = function(data) {
		// animate display
		var start_params = {
						top: self.container.position().top,
						left: self.container.position().left,
						width: self.container.width(),
						height: self.container.height()
					};

		// remove old menu if needed
		if (self.main_menu != null && self.main_menu.length > 0)
			self.main_menu.remove();

		// set new window content
		self.content.html(data);

		// reposition main menu
		self.main_menu = self.content.find('div.main_menu');
		if (self.main_menu.length > 0) 
			self.main_menu
					.detach()
					.insertAfter(self.title);

		var end_params = {
					top: start_params.top + Math.floor((start_params.height - self.container.height()) / 2),
					height: self.container.height()
				};

		// prevent window from going under menu
		if (end_params.top < 35)
			end_params.top = 35;

		// animate
		self.container
				.stop(true, true)
				.css(start_params)
				.animate(end_params, 400);

		// attach events
		self.attachEvents();

		// remove loading indicator
		self.container.removeClass('loading');

		// if display level is right, focus first element
		if (self.zIndex == 1000)
			self.focusInputElement();
	};

	/**
	 * Focus first input element if it exists
	 */
	self.focusInputElement = function() {
		var elements = self.content.find('input,select,textarea,button');

		if (elements.length > 0)
			elements.eq(0).focus();
	};

	/**
	 * Attach events to window content
	 */
	self.attachEvents = function() {
		self.content.find('form').each(function() {
			if ($(this).find('input:file').length == 0) {
				// normal case submission without file uploads
				$(this).submit(function(event) {
					event.preventDefault();
					self.submitForm(this);
				});

			} else {
				// submission with file uploads
				self.container.addClass('loading');

				// remove standard components and replace them with multi-language data on submit
				$(this).submit(function() {
					$(this).find('input.multi-language, textarea.multi-language').each(function() {
						var name = $(this).attr('name');
						var data = $(this).data('language');

						for (var language in data) {
							var hidden_field = $('<input>');

							hidden_field
									.attr('type', 'hidden')
									.attr('name', name + '_' + language)
									.val(data[language])
									.insertAfter($(this));
						}
					});
				});

				var iframe = self.getUploaderFrame();
				$(this).attr('target', iframe.attr('id'));

				// handle frame load event
				iframe.one('load', function() {
					var content = iframe.contents().find('body');

					// trigger original form event
					self.contentLoaded(content.html());

					// reset frame content in order to prevent errors with other events
					content.html('');
				});
			}
		});

		return self;
	};

	/**
	 * Event fired when there was an error loading AJAX data.
	 *
	 * @param object request
	 * @param string status
	 * @param string error
	 */
	self.contentError = function(request, status, error) {
		// animate display
		var start_params = {
						top: self.container.position().top,
						left: self.container.position().left,
						width: self.container.width(),
						height: self.container.height()
					};

		self.content.html(status + ': ' + error);

		var end_params = {
					top: start_params.top + Math.floor((start_params.height - self.container.height()) / 2),
					height: self.container.height()
				};

		self.container
				.stop(true, true)
				.css(start_params)
				.animate(end_params, 400);

		// remove loading indicator
		self.container.removeClass('loading');
	};

	/**
	 * Return jQuery object of uploader frame. If frame doesn't exists, we crate it
	 *
	 * @return resource
	 */
	self.getUploaderFrame = function() {
		var result = $('iframe#file_upload_frame');

		if (result.length == 0) {
			// element does not exist, create it
			result = $('<iframe>');

			result
				.attr('name', 'file_upload_frame')
				.attr('id', 'file_upload_frame')
				.css('display', 'none')
				.appendTo($('body'));
		}

		return result;
	};

	// finish object initialization
	self.init();
}

/**
 * Window System
 */
function WindowSystem(container) {
	var self = this;  // used internally for events

	self.modal_dialog = null;
	self.modal_dialog_container = null;
	self.container = $(container);
	self.list = [];
	self.window_list = $('<ul>');

	/**
	 * Finish object initialization.
	 */
	self.init = function() {
		self.window_list
				.attr('id', 'window_list')
				.appendTo(self.container);
	};

	/**
	 * Open new window (or focus existing) and load content from specified URL
	 *
	 * @param string id
	 * @param integer width
	 * @param string title
	 * @param boolean can_close
	 * @param string url
	 * @return object
	 */
	self.openWindow = function(id, width, title, can_close, url) {
		if (self.windowExists(id)) {
			// window already exists, reload content and show it
			var window = self.getWindow(id);

			window
				.focus()
				.loadContent(url);

		} else {
			// window does not exist, create it
			var window = new Window(id, width, title, can_close, url, false);

			self.list[id] = window;
			window
				.attach(self)
				.show(true)
				.loadContent();
		}

		return window;
	};

	/**
	 * Attach window class to existing structure.
	 *
	 * @param string id
	 * @param integer width
	 * @param boolean can_close
	 */
	self.attachToStructure = function(id, width, can_close) {
		if (self.windowExists(id)) {
			// window already exists, reload content and show it
			self.getWindow(id).focus();

		} else {
			// window does not exist, create it
			var window = new Window(id, width, null, can_close, null, true);

			self.list[id] = window;
			window
				.attach(self)
				.attachEvents()
				.show(true);
		}
	};

	/**
	 * Close window.
	 *
	 * @param string id
	 * @return boolean
	 */
	self.closeWindow = function(id) {
		if (self.windowExists(id))
			self.getWindow(id).close();
	};

	/**
	 * Remove window from list and container.
	 *
	 * @param object window
	 */
	self.removeWindow = function(window) {
		delete self.list[window.id];
		window.container.remove();
	};

	/**
	 * Load window content from specified URL.
	 *
	 * @param string id
	 * @param string url
	 */
	self.loadWindowContent = function(id, url) {
		if (self.windowExists(id))
			self.getWindow(id).loadContent(url);
	};

	/**
	 * Focuses specified window.
	 *
	 * @param string id
	 */
	self.focusWindow = function(id) {
		if (self.windowExists(id)) 
			for (var window_id in self.list)
				if (window_id != id)
					self.list[window_id].loseFocus(); else
					self.list[window_id].gainFocus();
	};

	/**
	 * Focuses top level window.
	 */
	self.focusTopWindow = function() {
		var highest_id = self.getTopWindowId();

		if (highest_id != null)
			self.focusWindow(highest_id);
	};

	/**
	 * Get window based on text id.
	 *
	 * @param string id
	 * @return object
	 */
	self.getWindow = function(id) {
		return self.list[id];
	};
	
	/**
	 * Get top window object.
	 * 
	 * @return object
	 */
	self.getTopWindow = function() {
		return self.getWindow(self.getTopWindowId());
	};
	
	/**
	 * Get top window id.
	 * 
	 * @return string
	 */
	self.getTopWindowId = function() {
		var highest_id = null;
		var highest_index = 0;

		for (var window_id in self.list)
			if (self.list[window_id].zIndex > highest_index) {
				highest_id = self.list[window_id].id;
				highest_index = self.list[window_id].zIndex;
			}

		return highest_id;
	};

	/**
	 * Check if window exists.
	 *
	 * @param string id
	 * @return boolean
	 */
	self.windowExists = function(id) {
		return id in self.list;
	};

	/**
	 * Block backend, show modal dialog and return container.
	 *
	 * @return jquery object
	 */
	self.showModalDialog = function() {
		self.modal_dialog_container.css({
						top: Math.round(($(document).height() - self.modal_dialog_container.height()) / 2),
						left: Math.round(($(document).width() - self.modal_dialog_container.width()) / 2)
					});

		self.modal_dialog
					.css({
						opacity: 0,
						display: 'block'
					})
					.animate({opacity: 1}, 500);
	};

	/**
	 * Hide modal dialog and clear its content.
	 */
	self.hideModalDialog = function() {
		self.modal_dialog.animate({opacity: 0}, 500, function() {
			$(this).css('display', 'none');
			self.modal_dialog_container.html('');
		});
	};

	// finish object initialization
	self.init();
}

$(function() {
	window_system = new WindowSystem('#wrap');
});