/**
 * Bundles plugin for Craft CMS
 *
 * Bundles JS
 *
 * @author    Kurious Agency
 * @copyright Copyright (c) 2019 Kurious Agency
 * @link      https://kurious.agency
 * @package   Bundles
 * @since     1.0.0
 */

(function($){

	if (typeof Craft.Bundles === 'undefined') {
		Craft.Bundles = {};
	}
	
	Craft.Bundles.BundleIndex = Craft.BaseElementIndex.extend(
	{
		editableBundleTypes: null,
		$newBundleBtnBundleType: null,
		$newBundleBtn: null,
	
		init: function(elementType, $container, settings) {

			this.on('selectSource', $.proxy(this, 'updateButton'));
			this.on('selectSite', $.proxy(this, 'updateButton'));
			this.base(elementType, $container, settings);
		},
	
		afterInit: function() {
			// Find which of the visible bundleTypes the user has permission to create new bundles in
			this.editableBundleTypes = [];
	
			for (var i = 0; i < Craft.Bundles.editableBundleTypes.length; i++) {
				var bundleType = Craft.Bundles.editableBundleTypes[i];
	
				if (this.getSourceByKey('bundleType:' + bundleType.id)) {
					this.editableBundleTypes.push(bundleType);
				}
			}
	
			this.base();
		},
	
		getDefaultSourceKey: function() {
			// Did they request a specific bundleType in the URL?
			if (this.settings.context === 'index' && typeof defaultBundleTypeHandle !== 'undefined') {
				for (var i = 0; i < this.$sources.length; i++) {
					var $source = $(this.$sources[i]);
	
					if ($source.data('handle') === defaultBundleTypeHandle) {
						return $source.data('key');
					}
				}
			}
	
			return this.base();
		},
	
		updateButton: function() {
			if (!this.$source) {
				return;
			}
	
			// Get the handle of the selected source
			var selectedSourceHandle = this.$source.data('handle');
	
			var i, href, label;
	
			// Update the New Bundle button
			// ---------------------------------------------------------------------
	
			if (this.editableBundleTypes.length) {
				// Remove the old button, if there is one
				if (this.$newBundleBtnBundleType) {
					this.$newBundleBtnBundleType.remove();
				}
	
				// Determine if they are viewing a bundleType that they have permission to create bundles in
				var selectedbundleType;
	
				if (selectedSourceHandle) {
					for (i = 0; i < this.editableBundleTypes.length; i++) {
						if (this.editableBundleTypes[i].handle === selectedSourceHandle) {
							selectedbundleType = this.editableBundleTypes[i];
							break;
						}
					}
				}
	
				this.$newBundleBtnBundleType = $('<div class="btngroup submit"/>');
				var $menuBtn;
	
				// If they are, show a primary "New Bundle" button, and a dropdown of the other bundleTypes (if any).
				// Otherwise only show a menu button
				if (selectedbundleType) {
					href = this._getbundleTypeTriggerHref(selectedbundleType);
					label = (this.settings.context === 'index' ? Craft.t('app', 'New Bundle') : Craft.t('app', 'New {bundleType} Bundle', {bundleType: selectedbundleType.name}));
					this.$newBundleBtn = $('<a class="btn submit add icon" ' + href + '>' + Craft.escapeHtml(label) + '</a>').appendTo(this.$newBundleBtnBundleType);
	
					if (this.settings.context !== 'index') {
						this.addListener(this.$newBundleBtn, 'click', function(ev) {
							this._openCreateBundleModal(ev.currentTarget.getAttribute('data-id'));
						});
					}
	
					if (this.editableBundleTypes.length > 1) {
						$menuBtn = $('<div class="btn submit menubtn"></div>').appendTo(this.$newBundleBtnBundleType);
					}
				}
				else {
					this.$newBundleBtn = $menuBtn = $('<div class="btn submit add icon menubtn">' + Craft.t('app', 'New Bundle') + '</div>').appendTo(this.$newBundleBtnBundleType);
				}
	
				if ($menuBtn) {
					var menuHtml = '<div class="menu"><ul>';
	
					for (i = 0; i < this.editableBundleTypes.length; i++) {
						var bundleType = this.editableBundleTypes[i];
	
						if (this.settings.context === 'index' || bundleType !== selectedbundleType) {
							href = this._getbundleTypeTriggerHref(bundleType);
							label = (this.settings.context === 'index' ? bundleType.name : Craft.t('app', 'New {bundleType} Bundle', {bundleType: bundleType.name}));
							menuHtml += '<li><a ' + href + '">' + Craft.escapeHtml(label) + '</a></li>';
						}
					}
	
					menuHtml += '</ul></div>';
	
					$(menuHtml).appendTo(this.$newBundleBtnBundleType);
					var menuBtn = new Garnish.MenuBtn($menuBtn);
	
					if (this.settings.context !== 'index') {
						menuBtn.on('optionSelect', $.proxy(function(ev) {
							this._openCreateBundleModal(ev.option.getAttribute('data-id'));
						}, this));
					}
				}
	
				this.addButton(this.$newBundleBtnBundleType);
			}
	
			// Update the URL if we're on the Bundles index
			// ---------------------------------------------------------------------
	
			if (this.settings.context === 'index' && typeof history !== 'undefined') {
				var uri = 'commerce-bundles/bundles';
	
				if (selectedSourceHandle) {
					uri += '/' + selectedSourceHandle;
				}
	
				history.replaceState({}, '', Craft.getUrl(uri));
			}
		},
	
		_getbundleTypeTriggerHref: function(bundleType) {
			if (this.settings.context === 'index') {
				var uri = 'commerce-bundles/bundles/' + bundleType.handle + '/new';
				if (this.siteId && this.siteId != Craft.primarySiteId) {
					for (var i = 0; i < Craft.sites.length; i++) {
						if (Craft.sites[i].id == this.siteId) {
							uri += '/'+Craft.sites[i].handle;
						}
					}
				}
				return 'href="' + Craft.getUrl(uri) + '"';
			}
			else {
				return 'data-id="' + bundleType.id + '"';
			}
		},
	
		_openCreateBundleModal: function(bundleTypeId) {
			if (this.$newBundleBtn.hasClass('loading')) {
				return;
			}
	
			// Find the bundleType
			var bundleType;
	
			for (var i = 0; i < this.editableBundleTypes.length; i++) {
				if (this.editableBundleTypes[i].id == bundleTypeId) {
					bundleType = this.editableBundleTypes[i];
					break;
				}
			}
	
			if (!bundleType) {
				return;
			}
	
			this.$newBundleBtn.addClass('inactive');
			var newBundleBtnText = this.$newBundleBtn.text();
			this.$newBundleBtn.text(Craft.t('app', 'New {bundleType} Bundle', {bundleType: bundleType.name}));
	
			Craft.createElementEditor(this.elementType, {
				hudTrigger: this.$newBundleBtnBundleType,
				elementType: 'kuriousagency\\commerce\\bundles\\elements\\Bundle',
				siteId: this.siteId,
				attributes: {
					bundleTypeId: bundleTypeId
				},
				onBeginLoading: $.proxy(function() {
					this.$newBundleBtn.addClass('loading');
				}, this),
				onEndLoading: $.proxy(function() {
					this.$newBundleBtn.removeClass('loading');
				}, this),
				onHideHud: $.proxy(function() {
					this.$newBundleBtn.removeClass('inactive').text(newBundleBtnText);
				}, this),
				onSaveElement: $.proxy(function(response) {
					// Make sure the right bundleType is selected
					var bundleTypeSourceKey = 'bundleType:' + bundleTypeId;
	
					if (this.sourceKey !== bundleTypeSourceKey) {
						this.selectSourceByKey(bundleTypeSourceKey);
					}
	
					this.selectElementAfterUpdate(response.id);
					this.updateElements();
				}, this)
			});
		}
	});
	
	// Register it!
	Craft.registerElementIndexClass('kuriousagency\\commerce\\bundles\\elements\\Bundle', Craft.Bundles.BundleIndex);
	
	})(jQuery);