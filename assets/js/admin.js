/**
 * SnipDrop Admin JavaScript
 *
 * @package SnipDrop
 * @since   1.0.0
 */

( function( $ ) {
	'use strict';

	/**
	 * Escape a string for safe insertion into HTML.
	 */
	function escHtml( str ) {
		var div = document.createElement( 'div' );
		div.appendChild( document.createTextNode( str ) );
		return div.innerHTML;
	}

	/**
	 * Escape a string for safe insertion into an HTML attribute.
	 */
	function escAttr( str ) {
		return escHtml( str ).replace( /"/g, '&quot;' ).replace( /'/g, '&#39;' );
	}

	var SNDP_Admin = {

		/**
		 * Code editor instance for custom snippets.
		 */
		editor: null,

		/**
		 * Current library state.
		 */
		libraryState: {
			category: '',
			search: '',
			tag: '',
			page: 1,
			perPage: 30,
			total: 0,
			pages: 0,
			loading: false
		},

		/**
		 * Search debounce timer.
		 */
		searchTimer: null,

		/**
		 * Initialize.
		 */
		init: function() {
			this.bindEvents();
			this.initCodeEditor();
			this.initLibrary();
		},

		/**
		 * Show an inline admin notice instead of SNDP_Admin.showNotice().
		 */
		showNotice: function( message, type ) {
			type = type || 'error';
			var $wrap = $( '.wrap' ).first();
			if ( ! $wrap.length ) {
				$wrap = $( '#wpbody-content' );
			}
			var $notice = $( '<div class="notice notice-' + escAttr( type ) + ' is-dismissible sndp-inline-notice"><p>' + escHtml( message ) + '</p></div>' );
			$wrap.find( '.sndp-inline-notice' ).remove();
			$wrap.prepend( $notice );
			if ( wp && wp.a11y && wp.a11y.speak ) {
				wp.a11y.speak( message );
			}
			setTimeout( function() {
				$notice.fadeOut( 300, function() { $( this ).remove(); } );
			}, 6000 );
		},

		/**
		 * Bind events.
		 */
		bindEvents: function() {
			// Library snippets.
			$( document ).on( 'change', '.sndp-toggle-input', this.toggleSnippet );
			$( '#sndp-sync-library' ).on( 'click', this.syncLibrary );
			$( document ).on( 'click', '.sndp-view-code', this.viewCode );
			$( document ).on( 'click', '.sndp-configure-snippet', this.openConfigureModal );
			$( document ).on( 'click', '.sndp-copy-to-custom', this.copyToCustom );
			$( '#sndp-configure-form' ).on( 'submit', this.saveConfiguration );
			$( document ).on( 'click', '.sndp-configure-reset', this.resetConfiguration );

			// Library search and filter.
			$( '#sndp-search-input' ).on( 'input', this.handleSearch );
			$( document ).on( 'click', '.sndp-category-link', this.handleCategoryClick );
			$( document ).on( 'click', '.sndp-tag-link', this.handleTagClick );
			$( document ).on( 'click', '.sndp-clear-tag-filter', this.clearTagFilter );
			$( document ).on( 'click', '.sndp-page-btn', this.handlePageClick );
			$( document ).on( 'click', '.sndp-load-more', this.handleLoadMore );

			// Custom snippets.
			$( document ).on( 'change', '.sndp-custom-toggle', this.toggleCustomSnippet );
			$( document ).on( 'click', '.sndp-delete-custom', this.deleteCustomSnippet );
			$( document ).on( 'click', '.sndp-duplicate-custom', this.duplicateCustomSnippet );

			// Add/Edit snippet form.
			$( '#sndp-snippet-form' ).on( 'submit', this.saveCustomSnippet );
			$( document ).on( 'click', '.sndp-save-activate-snippet', this.saveAndActivate );
			$( 'input[name="code_type"]' ).on( 'change', this.togglePhpOptions );
			$( '#sndp-snippet-location' ).on( 'change', this.toggleConditionalOptions );
			$( '#sndp-enable-conditions' ).on( 'change', this.toggleConditionsPanel );

			// Modals.
			$( document ).on( 'click', '.sndp-modal-close, .sndp-modal', this.closeModal );
			$( document ).on( 'click', '.sndp-modal-content', function( e ) {
				e.stopPropagation();
			} );

			// Copy code button.
			$( document ).on( 'click', '.sndp-copy-code', this.copyCode );

			// Copy shortcode button.
			$( document ).on( 'click', '.sndp-copy-shortcode', this.copyShortcode );

			// Retry fetch buttons.
			$( document ).on( 'click', '.sndp-retry-fetch', this.retryFetch );
			$( document ).on( 'click', '.sndp-sync-and-retry', this.syncAndRetry );

			// Custom snippets search and bulk actions.
			$( '#sndp-custom-search' ).on( 'input', this.filterCustomSnippets );
			$( '#sndp-select-all' ).on( 'change', this.handleSelectAll );
			$( document ).on( 'change', '.sndp-bulk-check', this.handleBulkCheckChange );
			$( '#sndp-bulk-apply' ).on( 'click', this.handleBulkApply );

			// Import/Export.
			$( '#sndp-import-trigger' ).on( 'click', this.showImportForm );
			$( '#sndp-import-cancel' ).on( 'click', this.hideImportForm );
			$( '#sndp-import-file' ).on( 'change', this.handleImportFileSelect );
			$( '#sndp-import-upload' ).on( 'submit', this.handleImportSubmit );

			// View toggle.
			$( document ).on( 'click', '.sndp-view-btn', this.handleViewToggle );

			// Global Header & Footer.
			$( '#sndp-global-scripts-form' ).on( 'submit', this.saveGlobalScripts );

			// Revisions.
			$( document ).on( 'click', '.sndp-restore-revision', this.handleRestoreRevision );
			$( document ).on( 'click', '.sndp-restore-global-revision', this.handleRestoreGlobalRevision );
			$( document ).on( 'click', '.sndp-view-diff', this.handleViewDiff );

			// ESC key to close modals.
			$( document ).on( 'keyup', function( e ) {
				if ( 27 === e.keyCode ) {
					SNDP_Admin.closeModal();
					SNDP_Admin.closeConfirm();
				}
			} );

			// Close confirm modal on overlay click.
			$( '#sndp-confirm-modal' ).on( 'click', function( e ) {
				if ( $( e.target ).is( '#sndp-confirm-modal' ) ) {
					SNDP_Admin.closeConfirm();
				}
			} );
			$( '#sndp-confirm-modal .sndp-modal-close' ).on( 'click', function() {
				SNDP_Admin.closeConfirm();
			} );

			// Safe mode confirmation.
			$( '#sndp_safe_mode' ).on( 'change', function( e ) {
				var $cb     = $( this );
				var turning = $cb.prop( 'checked' );

				$cb.prop( 'checked', ! turning );

				if ( turning ) {
					SNDP_Admin.confirmModal( {
						icon: 'shield',
						type: 'warning',
						title: sndp_admin.strings.safe_mode_enable_title || 'Enable Safe Mode',
						message: '<p>' + ( sndp_admin.strings.safe_mode_enable_desc || 'All snippets will be disabled immediately. Your site will not execute any custom code until safe mode is turned off.' ) + '</p>',
						buttons: [
							{ label: sndp_admin.strings.cancel, class: '', id: 'sndp-cm-cancel' },
							{ label: sndp_admin.strings.safe_mode_enable_btn || 'Enable Safe Mode', class: 'button-primary', id: 'sndp-cm-confirm' }
						],
						handlers: {
							'sndp-cm-cancel': function() { SNDP_Admin.closeConfirm(); },
							'sndp-cm-confirm': function() {
								$cb.prop( 'checked', true );
								SNDP_Admin.closeConfirm();
							}
						}
					} );
				} else {
					SNDP_Admin.confirmModal( {
						icon: 'shield',
						type: 'success',
						title: sndp_admin.strings.safe_mode_disable_title || 'Disable Safe Mode',
						message: '<p>' + ( sndp_admin.strings.safe_mode_disable_desc || 'All active snippets will start executing again. Make sure any problematic snippets have been fixed or deactivated.' ) + '</p>',
						buttons: [
							{ label: sndp_admin.strings.cancel, class: '', id: 'sndp-cm-cancel' },
							{ label: sndp_admin.strings.safe_mode_disable_btn || 'Disable Safe Mode', class: 'button-primary', id: 'sndp-cm-confirm' }
						],
						handlers: {
							'sndp-cm-cancel': function() { SNDP_Admin.closeConfirm(); },
							'sndp-cm-confirm': function() {
								$cb.prop( 'checked', false );
								SNDP_Admin.closeConfirm();
							}
						}
					} );
				}
			} );

			// Page picker.
			$( '#sndp-page-search' ).on( 'input', this.handlePageSearch );
			$( document ).on( 'click', '.sndp-page-result', this.handlePageSelect );
			$( document ).on( 'click', '.sndp-page-tag-remove', this.handlePageRemove );
			$( document ).on( 'click', function( e ) {
				if ( ! $( e.target ).closest( '.sndp-page-picker' ).length ) {
					$( '#sndp-page-search-results' ).empty().hide();
				}
			} );

			// Ctrl+S / Cmd+S to save custom snippet.
			$( document ).on( 'keydown', function( e ) {
				if ( ( e.ctrlKey || e.metaKey ) && 83 === e.keyCode ) {
					var $form = $( '#sndp-snippet-form' );
					if ( $form.length ) {
						e.preventDefault();
						$form.trigger( 'submit' );
					}
				}
			} );
		},

		/**
		 * Initialize library (load snippets via AJAX).
		 */
		initLibrary: function() {
			if ( $( '#sndp-snippets-grid' ).length ) {
				// Restore saved view preference.
				var savedView = localStorage.getItem( 'sndp_view_mode' ) || 'grid';
				if ( 'list' === savedView ) {
					$( '#sndp-snippets-grid' ).addClass( 'sndp-list-view' );
					$( '.sndp-view-btn' ).removeClass( 'active' );
					$( '.sndp-view-btn[data-view="list"]' ).addClass( 'active' );
				}
				this.loadSnippets();
			}
		},

		/**
		 * Load snippets via AJAX.
		 */
		loadSnippets: function( append ) {
			var self = this;
			append = append || false;

			if ( self.libraryState.loading ) {
				return;
			}

			self.libraryState.loading = true;
			var $grid = $( '#sndp-snippets-grid' );

			if ( ! append ) {
				$grid.html( '<div class="sndp-loading"><span class="spinner is-active"></span><p>' + ( sndp_admin.strings.loading || 'Loading snippets...' ) + '</p></div>' );
			}

			$.ajax( {
				url: sndp_admin.ajax_url,
				type: 'POST',
				data: {
					action: 'sndp_load_snippets',
					nonce: sndp_admin.nonce,
					category: self.libraryState.category,
					search: self.libraryState.search,
					tag: self.libraryState.tag,
					page: self.libraryState.page,
					per_page: self.libraryState.perPage
				},
				success: function( response ) {
					if ( response.success ) {
						self.libraryState.total = response.data.total;
						self.libraryState.pages = response.data.pages;
						self.renderSnippets( response.data.snippets, append );
						self.renderPagination();
						self.updateResultsInfo();
					} else {
						$grid.html( '<div class="sndp-no-snippets"><p>' + ( response.data.message || sndp_admin.strings.error ) + '</p></div>' );
					}
				},
				error: function() {
					$grid.html( '<div class="sndp-no-snippets"><p>' + sndp_admin.strings.error + '</p></div>' );
				},
				complete: function() {
					self.libraryState.loading = false;
				}
			} );
		},

		/**
		 * Render snippets to the grid.
		 */
		renderSnippets: function( snippets, append ) {
			var $grid = $( '#sndp-snippets-grid' );
			var template = wp.template( 'sndp-snippet-card' );

			if ( ! append ) {
				$grid.empty();
			} else {
				$grid.find( '.sndp-load-more-container' ).remove();
			}

			if ( snippets.length === 0 && ! append ) {
				$grid.html( '<div class="sndp-no-snippets"><p>' + ( sndp_admin.strings.no_snippets || 'No snippets found.' ) + '</p></div>' );
				return;
			}

			snippets.forEach( function( snippet ) {
				$grid.append( template( snippet ) );
			} );
		},

		/**
		 * Render pagination.
		 */
		renderPagination: function() {
			var self = this;
			var $pagination = $( '#sndp-pagination' );
			$pagination.empty();

			if ( self.libraryState.pages <= 1 ) {
				return;
			}

			var html = '<div class="sndp-pagination-inner">';

			// Previous button.
			if ( self.libraryState.page > 1 ) {
				html += '<button type="button" class="button sndp-page-btn" data-page="' + ( self.libraryState.page - 1 ) + '">&laquo; ' + ( sndp_admin.strings.prev || 'Previous' ) + '</button>';
			}

			// Page info.
			html += '<span class="sndp-page-info">';
			html += ( sndp_admin.strings.page || 'Page' ) + ' ' + self.libraryState.page + ' / ' + self.libraryState.pages;
			html += '</span>';

			// Next button.
			if ( self.libraryState.page < self.libraryState.pages ) {
				html += '<button type="button" class="button sndp-page-btn" data-page="' + ( self.libraryState.page + 1 ) + '">' + ( sndp_admin.strings.next || 'Next' ) + ' &raquo;</button>';
			}

			html += '</div>';
			$pagination.html( html );
		},

		/**
		 * Update results info.
		 */
		updateResultsInfo: function() {
			var self = this;
			var $info = $( '#sndp-results-info .sndp-showing' );
			var start = ( ( self.libraryState.page - 1 ) * self.libraryState.perPage ) + 1;
			var end = Math.min( start + self.libraryState.perPage - 1, self.libraryState.total );

			if ( self.libraryState.total === 0 ) {
				$info.text( sndp_admin.strings.no_results || 'No results found.' );
			} else {
				$info.text(
					( sndp_admin.strings.showing || 'Showing' ) + ' ' + start + '-' + end + ' ' +
					( sndp_admin.strings.of || 'of' ) + ' ' + self.libraryState.total + ' ' +
					( sndp_admin.strings.snippets || 'snippets' )
				);
			}
		},

		/**
		 * Handle search input.
		 */
		handleSearch: function() {
			var self = SNDP_Admin;
			var query = $( this ).val().trim();

			clearTimeout( self.searchTimer );

			self.searchTimer = setTimeout( function() {
				self.libraryState.search = query;
				self.libraryState.page = 1;
				self.loadSnippets();
			}, 300 );
		},

		/**
		 * Handle category click.
		 */
		handleCategoryClick: function( e ) {
			e.preventDefault();
			var self = SNDP_Admin;
			var $link = $( this );
			var category = $link.data( 'category' ) || '';

			// Update active state.
			$( '.sndp-category-link' ).removeClass( 'active' );
			$link.addClass( 'active' );

			// Load snippets.
			self.libraryState.category = category;
			self.libraryState.page = 1;
			self.loadSnippets();
		},

		/**
		 * Clear active tag filter.
		 */
		clearTagFilter: function( e ) {
			e.preventDefault();
			SNDP_Admin.libraryState.tag = '';
			SNDP_Admin.libraryState.page = 1;
			$( '.sndp-active-tag-filter' ).remove();
			SNDP_Admin.loadSnippets();
		},

		/**
		 * Handle tag click for library filtering.
		 */
		handleTagClick: function( e ) {
			e.preventDefault();
			e.stopPropagation();
			var self = SNDP_Admin;
			var tag = $( this ).data( 'tag' ) || '';

			if ( self.libraryState.tag === tag ) {
				self.libraryState.tag = '';
				$( '.sndp-active-tag-filter' ).remove();
			} else {
				self.libraryState.tag = tag;
				self.libraryState.category = '';
				$( '.sndp-category-link' ).removeClass( 'active' );
				$( '.sndp-category-link[data-category=""]' ).addClass( 'active' );

				$( '.sndp-active-tag-filter' ).remove();
				var filterHtml = '<span class="sndp-active-tag-filter">';
				filterHtml += escHtml( tag );
				filterHtml += ' <button type="button" class="sndp-clear-tag-filter">&times;</button>';
				filterHtml += '</span>';
				$( '#sndp-search-input' ).after( filterHtml );
			}

			self.libraryState.page = 1;
			self.loadSnippets();
		},

		/**
		 * Handle pagination click.
		 */
		handlePageClick: function( e ) {
			e.preventDefault();
			var self = SNDP_Admin;
			var page = $( this ).data( 'page' );

			if ( page && page !== self.libraryState.page ) {
				self.libraryState.page = page;
				self.loadSnippets();

				// Scroll to top of grid.
				$( 'html, body' ).animate( {
					scrollTop: $( '#sndp-snippets-grid' ).offset().top - 50
				}, 200 );
			}
		},

		/**
		 * Handle load more click.
		 */
		handleLoadMore: function( e ) {
			e.preventDefault();
			var self = SNDP_Admin;

			if ( self.libraryState.page < self.libraryState.pages ) {
				self.libraryState.page++;
				self.loadSnippets( true );
			}
		},

		/**
		 * Initialize code editor.
		 */
		initCodeEditor: function() {
			var $textarea = $( '#sndp-snippet-code' );

			if ( $textarea.length && sndp_admin.editor_settings && Object.keys( sndp_admin.editor_settings ).length ) {
				this.editor = wp.codeEditor.initialize( $textarea, sndp_admin.editor_settings );
			}

			// Auto-detect code type on first paste into an empty editor.
			this.codeAutoDetected = false;
			$textarea.on( 'paste', function() {
				if ( SNDP_Admin.codeAutoDetected ) {
					return;
				}
				var current = $textarea.val().trim();
				if ( current.length > 0 ) {
					return;
				}
				setTimeout( function() {
					var pasted = $textarea.val().trim();
					var detected = SNDP_Admin.detectCodeType( pasted );
					if ( detected ) {
						$( 'input[name="code_type"][value="' + detected + '"]' ).prop( 'checked', true ).trigger( 'change' );
						SNDP_Admin.codeAutoDetected = true;
					}
				}, 50 );
			} );

			// Also handle paste in CodeMirror.
			if ( this.editor && this.editor.codemirror ) {
				this.editor.codemirror.on( 'paste', function( cm ) {
					if ( SNDP_Admin.codeAutoDetected ) {
						return;
					}
					var current = cm.getValue().trim();
					if ( current.length > 0 ) {
						return;
					}
					setTimeout( function() {
						var pasted = cm.getValue().trim();
						var detected = SNDP_Admin.detectCodeType( pasted );
						if ( detected ) {
							$( 'input[name="code_type"][value="' + detected + '"]' ).prop( 'checked', true ).trigger( 'change' );
							SNDP_Admin.codeAutoDetected = true;
						}
					}, 50 );
				} );
			}

			// Initialize CodeMirror for global Header & Footer editors.
			$( '.sndp-global-editor' ).each( function() {
				if ( sndp_admin.editor_settings && Object.keys( sndp_admin.editor_settings ).length ) {
					var settings = $.extend( true, {}, sndp_admin.editor_settings );
					settings.codemirror = $.extend( {}, settings.codemirror, {
						mode: 'htmlmixed'
					} );
					wp.codeEditor.initialize( $( this ), settings );
				}
			} );

			// Dark mode toggle.
			this.initDarkMode();

			this.initDatepickers();
			this.togglePhpOptions();
			this.toggleConditionalOptions();
			this.initUnsavedWarning();
		},

		/**
		 * Initialize dark mode toggle for the code editor.
		 */
		initDarkMode: function() {
			var $toggle = $( '#sndp-dark-mode-toggle' );
			var $field  = $( '.sndp-code-field' );

			if ( ! $toggle.length || ! $field.length ) {
				return;
			}

			var isDark = localStorage.getItem( 'sndp_editor_dark' ) === '1';

			if ( isDark ) {
				$field.addClass( 'sndp-dark-editor' );
				$toggle.addClass( 'active' );
				if ( this.editor && this.editor.codemirror ) {
					this.editor.codemirror.refresh();
				}
			}

			$toggle.on( 'click', function() {
				isDark = ! isDark;
				$field.toggleClass( 'sndp-dark-editor', isDark );
				$toggle.toggleClass( 'active', isDark );
				localStorage.setItem( 'sndp_editor_dark', isDark ? '1' : '0' );

				if ( SNDP_Admin.editor && SNDP_Admin.editor.codemirror ) {
					SNDP_Admin.editor.codemirror.refresh();
				}
			} );
		},

		/**
		 * Initialize jQuery UI datepickers for schedule fields.
		 */
		initDatepickers: function() {
			if ( ! $.fn.datepicker ) {
				return;
			}

			$( '.sndp-datepicker' ).datepicker( {
				dateFormat: 'yy-mm-dd',
				changeMonth: true,
				changeYear: true,
				beforeShow: function( input, inst ) {
					inst.dpDiv.addClass( 'sndp-datepicker-popup' );
				}
			} );
		},

		/**
		 * Track form changes and warn before navigating away with unsaved edits.
		 */
		initUnsavedWarning: function() {
			var $form = $( '#sndp-snippet-form' );
			if ( ! $form.length ) {
				return;
			}

			this.formDirty = false;
			this.formSaving = false;
			var self = this;

			$form.on( 'change input', 'input, textarea, select', function() {
				self.formDirty = true;
			} );

			if ( this.editor ) {
				this.editor.codemirror.on( 'change', function() {
					self.formDirty = true;
				} );
			}

			$form.on( 'submit', function() {
				self.formSaving = true;
			} );

			$( '.sndp-save-activate-snippet' ).on( 'click', function() {
				self.formSaving = true;
			} );

			$( window ).on( 'beforeunload', function( e ) {
				if ( self.formDirty && ! self.formSaving ) {
					e.preventDefault();
					return '';
				}
			} );
		},

		/**
		 * Combine separate date and time inputs into a single datetime-local value.
		 */
		getScheduleValue: function( which ) {
			var date = $( '#sndp-schedule-' + which + '-date' ).val();
			var time = $( '#sndp-schedule-' + which + '-time' ).val();

			if ( ! date ) {
				return '';
			}

			return time ? date + 'T' + time : date + 'T00:00';
		},

		detectCodeType: function( code ) {
			if ( ! code ) {
				return null;
			}
			if ( /^<\?php\b/i.test( code ) || /\bfunction\s+\w+\s*\(/.test( code ) || /\badd_action\s*\(/.test( code ) || /\badd_filter\s*\(/.test( code ) ) {
				return 'php';
			}
			if ( /^<script[\s>]/i.test( code ) || /\bdocument\.getElementById\b/.test( code ) || /\bjQuery\s*\(/.test( code ) || /\bconsole\.log\b/.test( code ) ) {
				return 'js';
			}
			if ( /^<style[\s>]/i.test( code ) || /\{[\s\S]*?[a-z-]+\s*:\s*[^;]+;/.test( code ) || /^[.#@][a-zA-Z]/.test( code ) ) {
				return 'css';
			}
			if ( /^<(!DOCTYPE|html|div|p|span|a\s|h[1-6]|section|header|footer|nav|main|article)/i.test( code ) ) {
				return 'html';
			}
			return null;
		},

		/**
		 * Toggle PHP options visibility based on code type.
		 */
		togglePhpOptions: function() {
			var codeType = $( 'input[name="code_type"]:checked' ).val();
			var $phpOptions = $( '.sndp-php-options' );

			if ( 'php' === codeType ) {
				$phpOptions.removeClass( 'hidden' );
			} else {
				$phpOptions.addClass( 'hidden' );
			}

			// Update the code editor hint for the selected type.
			var $hint = $( '#sndp-code-hint' );
			if ( $hint.length ) {
				var hints = {
					php:  sndp_admin.strings.hint_php  || '',
					js:   sndp_admin.strings.hint_js   || '',
					css:  sndp_admin.strings.hint_css  || '',
					html: sndp_admin.strings.hint_html || ''
				};
				$hint.html( hints[ codeType ] || hints.php );
			}

			// Toggle conditional options based on location.
			SNDP_Admin.toggleConditionalOptions();
		},

		/**
		 * Toggle conditional options visibility based on location.
		 */
		toggleConditionalOptions: function() {
			var location = $( '#sndp-snippet-location' ).val();
			var $frontendOptions = $( '.sndp-frontend-only' );
			var $shortcodeHint = $( '#sndp-shortcode-hint' );
			var $paragraphHint = $( '#sndp-paragraph-hint' );
			var snippetId = $( '#sndp-snippet-id' ).val();

			// Hide frontend options for admin-only location.
			if ( 'admin' === location ) {
				$frontendOptions.addClass( 'hidden' );
			} else {
				$frontendOptions.removeClass( 'hidden' );
			}

			// Show/hide shortcode hint.
			if ( 'shortcode' === location && snippetId ) {
				$shortcodeHint.removeClass( 'hidden' );
			} else {
				$shortcodeHint.addClass( 'hidden' );
			}

			// Show/hide paragraph number.
			if ( 'after_paragraph' === location ) {
				$paragraphHint.removeClass( 'hidden' );
			} else {
				$paragraphHint.addClass( 'hidden' );
			}
		},

		/**
		 * Toggle conditions panel visibility.
		 */
		toggleConditionsPanel: function() {
			var $body = $( '.sndp-conditions-body' );
			if ( $( this ).is( ':checked' ) ) {
				$body.slideDown( 200 );
			} else {
				$body.slideUp( 200 );
			}
		},

		/**
		 * Toggle library snippet.
		 */
		toggleSnippet: function() {
			var $toggle = $( this );
			var $card = $toggle.closest( '.sndp-snippet-card' );
			var snippetId = $card.data( 'snippet-id' );

			if ( ! snippetId ) {
				return;
			}

			$toggle.prop( 'disabled', true );
			$card.addClass( 'is-toggling' );

			$.ajax( {
				url: sndp_admin.ajax_url,
				type: 'POST',
				data: {
					action: 'sndp_toggle_snippet',
					nonce: sndp_admin.nonce,
					snippet_id: snippetId
				},
				success: function( response ) {
					if ( response.success ) {
						if ( response.data.enabled ) {
							$card.addClass( 'enabled' ).removeClass( 'has-error' );
							$card.find( '.sndp-snippet-error' ).remove();
							$toggle.prop( 'checked', true );
						} else {
							$card.removeClass( 'enabled has-error' );
							$card.find( '.sndp-snippet-error' ).remove();
							$toggle.prop( 'checked', false );
						}

						// Update sidebar enabled count.
						if ( typeof response.data.enabled_count !== 'undefined' ) {
							$( '#sndp-enabled-count' ).text( response.data.enabled_count );
						}

						SNDP_Admin.showNotice( response.data.message, 'success' );

						// Show conflict warnings (non-blocking) after successful enable.
						if ( response.data.conflict_warnings && response.data.conflict_warnings.length ) {
							var conflictMsg = sndp_admin.strings.conflict_enable_warn + '\n' + response.data.conflict_warnings.join( '\n' ) + '\n\n' + sndp_admin.strings.conflict_proceed;
							SNDP_Admin.showNotice( conflictMsg, 'warning' );
						}
					} else {
						var errorMsg = response.data.message || sndp_admin.strings.error;

						// Compatibility issues from the checker.
						if ( response.data.compat_issues && response.data.compat_issues.length ) {
							errorMsg = sndp_admin.strings.compat_enable_block + '\n\n' + response.data.compat_issues.join( '\n' );
						}

						// Legacy: missing plugins fallback.
						if ( response.data.missing_plugins && response.data.missing_plugins.length ) {
							errorMsg = sndp_admin.strings.plugin_required + '\n\n' + response.data.missing_plugins.join( '\n' );
						}

						// Show error inline on the card for syntax errors.
						if ( response.data.syntax_error ) {
							$card.addClass( 'has-error' );
							$card.find( '.sndp-snippet-error' ).remove();
							$card.find( '.sndp-snippet-desc' ).after(
								'<div class="sndp-snippet-error"><span class="dashicons dashicons-warning"></span> ' + $( '<span/>' ).text( response.data.message ).html() + '</div>'
							);
						}

						SNDP_Admin.showNotice( errorMsg );
						$toggle.prop( 'checked', ! $toggle.prop( 'checked' ) );
					}
				},
				error: function() {
					SNDP_Admin.showNotice( sndp_admin.strings.error );
					$toggle.prop( 'checked', ! $toggle.prop( 'checked' ) );
				},
				complete: function() {
					$toggle.prop( 'disabled', false );
					$card.removeClass( 'is-toggling' );
				}
			} );
		},

		/**
		 * Sync library.
		 */
		syncLibrary: function( e ) {
			e.preventDefault();

			var $btn = $( this );

			if ( $btn.hasClass( 'loading' ) ) {
				return;
			}

			$btn.addClass( 'loading' ).prop( 'disabled', true );
			$btn.find( '.dashicons' ).after( '<span class="sndp-sync-text">' + sndp_admin.strings.syncing + '</span>' );

			$.ajax( {
				url: sndp_admin.ajax_url,
				type: 'POST',
				data: {
					action: 'sndp_sync_library',
					nonce: sndp_admin.nonce
				},
				success: function( response ) {
					if ( response.success ) {
						location.reload();
					} else {
						SNDP_Admin.showNotice( response.data.message || sndp_admin.strings.error );
					}
				},
				error: function() {
					SNDP_Admin.showNotice( sndp_admin.strings.error );
				},
				complete: function() {
					$btn.removeClass( 'loading' ).prop( 'disabled', false );
					$btn.find( '.sndp-sync-text' ).remove();
				}
			} );
		},

		/**
		 * View snippet code.
		 */
		viewCode: function( e ) {
			e.preventDefault();
			e.stopPropagation();

			var $btn = $( this );
			var snippetId = $btn.data( 'snippet-id' );
			var $card = $btn.closest( '.sndp-snippet-card' );
			var title = $card.find( '.sndp-snippet-title' ).text();

			SNDP_Admin.fetchSnippetCode( snippetId, title, $btn );
		},

		/**
		 * Fetch snippet code with retry support.
		 */
		fetchSnippetCode: function( snippetId, title, $btn, retryCount ) {
			retryCount = retryCount || 0;
			var maxRetries = 2;

			$btn.prop( 'disabled', true );

			// Show modal with loading state.
			SNDP_Admin.showCodeModalLoading( title );

			$.ajax( {
				url: sndp_admin.ajax_url,
				type: 'POST',
				data: {
					action: 'sndp_get_snippet_code',
					nonce: sndp_admin.nonce,
					snippet_id: snippetId
				},
				success: function( response ) {
					if ( response.success && response.data.code ) {
						SNDP_Admin.displaySnippetCode( response.data );
					} else {
						// Check if we should retry.
						if ( retryCount < maxRetries ) {
							setTimeout( function() {
								SNDP_Admin.fetchSnippetCode( snippetId, title, $btn, retryCount + 1 );
							}, 1000 );
						} else {
							SNDP_Admin.showCodeModalError(
								response.data.message || sndp_admin.strings.error,
								snippetId,
								title,
								$btn
							);
						}
					}
				},
				error: function() {
					// Retry on network errors.
					if ( retryCount < maxRetries ) {
						setTimeout( function() {
							SNDP_Admin.fetchSnippetCode( snippetId, title, $btn, retryCount + 1 );
						}, 1000 );
					} else {
						SNDP_Admin.showCodeModalError(
							sndp_admin.strings.connection_error || 'Unable to connect. Please check your internet connection and try again.',
							snippetId,
							title,
							$btn
						);
					}
				},
				complete: function() {
					$btn.prop( 'disabled', false );
				}
			} );
		},

		/**
		 * Show modal in loading state.
		 */
		showCodeModalLoading: function( title ) {
			$( '#sndp-modal-title' ).text( title );
			$( '#sndp-modal-description' ).empty().hide();
			$( '#sndp-modal-credits' ).hide();
			$( '#sndp-code-preview-wrapper' ).show();
			$( '#sndp-code-error' ).hide();
			$( '#sndp-line-numbers' ).text( '' );
			$( '#sndp-modal-code' ).html( '<span class="sndp-loading-text">Loading...</span>' );
			$( '#sndp-code-modal' ).addClass( 'sndp-modal-open' );
		},

		/**
		 * Display snippet code in modal.
		 */
		displaySnippetCode: function( data ) {
			// Hide error, show code.
			$( '#sndp-code-error' ).hide();
			$( '#sndp-code-preview-wrapper' ).show();

			// Display code with line numbers.
			var code = data.code.replace( /\n+$/, '' ); // Trim trailing newlines.
			var lines = code.split( '\n' );
			var lineNumbers = [];
			for ( var i = 1; i <= lines.length; i++ ) {
				lineNumbers.push( i );
			}
			$( '#sndp-line-numbers' ).text( lineNumbers.join( '\n' ) );
			$( '#sndp-modal-code' ).text( code );

			// Display long description if available.
			var $description = $( '#sndp-modal-description' );
			if ( data.long_description ) {
				var descHtml = data.long_description
					.split( /\n\n+/ )
					.map( function( p ) { return '<p>' + escHtml( p ).replace( /\n/g, '<br>' ) + '</p>'; } )
					.join( '' );
				$description.html( descHtml ).show();
			} else {
				$description.empty().hide();
			}

			// Display author and source credits.
			var $credits = $( '#sndp-modal-credits' );
			var $author = $( '#sndp-modal-author' );
			var $source = $( '#sndp-modal-source' );
			var hasCredits = false;

			$author.empty();
			$source.empty();

			if ( data.author && data.author.name ) {
				var authorHtml = '<strong>' + escHtml( sndp_admin.strings.author_label ) + '</strong> ';
				if ( data.author.url ) {
					authorHtml += '<a href="' + escAttr( data.author.url ) + '" target="_blank" rel="noopener noreferrer">' + escHtml( data.author.name ) + '</a>';
				} else {
					authorHtml += escHtml( data.author.name );
				}
				$author.html( authorHtml );
				hasCredits = true;
			}

			if ( data.source && data.source.name ) {
				var sourceHtml = '<strong>' + escHtml( sndp_admin.strings.source_label ) + '</strong> ';
				if ( data.source.url ) {
					sourceHtml += '<a href="' + escAttr( data.source.url ) + '" target="_blank" rel="noopener noreferrer">' + escHtml( data.source.name ) + '</a>';
				} else {
					sourceHtml += escHtml( data.source.name );
				}
				$source.html( sourceHtml );
				hasCredits = true;
			}

			if ( hasCredits ) {
				$credits.show();
			} else {
				$credits.hide();
			}
		},

		/**
		 * Show error in modal with retry option.
		 */
		showCodeModalError: function( message, snippetId, title, $btn ) {
			$( '#sndp-code-preview-wrapper' ).hide();
			$( '#sndp-modal-description' ).empty().hide();
			$( '#sndp-modal-credits' ).hide();

			var errorHtml = '<div class="sndp-error-icon"><span class="dashicons dashicons-warning"></span></div>';
			errorHtml += '<p class="sndp-error-message">' + escHtml( message ) + '</p>';
			errorHtml += '<p class="sndp-error-hint">' + escHtml( sndp_admin.strings.error_hint ) + '</p>';
			errorHtml += '<div class="sndp-error-actions">';
			errorHtml += '<button type="button" class="button sndp-retry-fetch" data-snippet-id="' + escAttr( snippetId ) + '" data-title="' + escAttr( title ) + '">' + escHtml( sndp_admin.strings.try_again ) + '</button>';
			errorHtml += '<button type="button" class="button sndp-sync-and-retry" data-snippet-id="' + escAttr( snippetId ) + '" data-title="' + escAttr( title ) + '">' + escHtml( sndp_admin.strings.sync_library ) + '</button>';
			errorHtml += '</div>';

			$( '#sndp-code-error' ).html( errorHtml ).show();
		},

		/**
		 * Retry fetching snippet code.
		 */
		retryFetch: function( e ) {
			e.preventDefault();
			var $btn = $( this );
			var snippetId = $btn.data( 'snippet-id' );
			var title = $btn.data( 'title' );
			var $originalBtn = $( '.sndp-view-code[data-snippet-id="' + snippetId + '"]' );

			$btn.prop( 'disabled', true ).text( sndp_admin.strings.loading_btn );
			SNDP_Admin.fetchSnippetCode( snippetId, title, $originalBtn );
		},

		/**
		 * Sync library and retry fetching.
		 */
		syncAndRetry: function( e ) {
			e.preventDefault();
			var $btn = $( this );
			var snippetId = $btn.data( 'snippet-id' );
			var title = $btn.data( 'title' );
			var $originalBtn = $( '.sndp-view-code[data-snippet-id="' + snippetId + '"]' );

			$btn.prop( 'disabled', true ).text( sndp_admin.strings.syncing );
			$( '.sndp-retry-fetch' ).prop( 'disabled', true );

			// First sync, then retry.
			$.ajax( {
				url: sndp_admin.ajax_url,
				type: 'POST',
				data: {
					action: 'sndp_sync_library',
					nonce: sndp_admin.nonce
				},
				success: function( response ) {
					// Retry fetching after sync.
					SNDP_Admin.fetchSnippetCode( snippetId, title, $originalBtn );
				},
				error: function() {
					$btn.prop( 'disabled', false ).text( 'Sync Library' );
					$( '.sndp-retry-fetch' ).prop( 'disabled', false );
				}
			} );
		},

		/**
		 * Open configure modal.
		 */
		openConfigureModal: function( e ) {
			e.preventDefault();
			e.stopPropagation();

			var $btn = $( this );
			var snippetId = $btn.data( 'snippet-id' );
			var $card = $btn.closest( '.sndp-snippet-card' );
			var title = $card.find( '.sndp-snippet-title' ).text();

			$btn.prop( 'disabled', true );

			$.ajax( {
				url: sndp_admin.ajax_url,
				type: 'POST',
				data: {
					action: 'sndp_get_snippet_settings',
					nonce: sndp_admin.nonce,
					snippet_id: snippetId
				},
				success: function( response ) {
					if ( response.success ) {
						$( '#sndp-configure-modal-title' ).text( 'Configure: ' + title );
						$( '#sndp-configure-snippet-id' ).val( snippetId );

						// Build form fields.
						var $fields = $( '#sndp-configure-fields' );
						$fields.empty();

						if ( response.data.settings && response.data.settings.length ) {
							$.each( response.data.settings, function( i, setting ) {
								var value = response.data.config[ setting.id ] || setting.default || '';
								var fieldHtml = SNDP_Admin.buildConfigField( setting, value );
								$fields.append( fieldHtml );
							} );
						}

						$( '#sndp-configure-modal' ).addClass( 'sndp-modal-open' );
					} else {
						SNDP_Admin.showNotice( response.data.message || sndp_admin.strings.error );
					}
				},
				error: function() {
					SNDP_Admin.showNotice( sndp_admin.strings.error );
				},
				complete: function() {
					$btn.prop( 'disabled', false );
				}
			} );
		},

		/**
		 * Build configuration field HTML.
		 */
		buildConfigField: function( setting, value ) {
			var safeId = escAttr( setting.id );
			var safeValue = escAttr( value );
			var html = '<div class="sndp-configure-field">';
			html += '<label for="sndp-config-' + safeId + '">' + escHtml( setting.label ) + '</label>';

			switch ( setting.type ) {
				case 'textarea':
					html += '<textarea id="sndp-config-' + safeId + '" name="' + safeId + '" rows="3">' + escHtml( value ) + '</textarea>';
					break;

				case 'select':
					html += '<select id="sndp-config-' + safeId + '" name="' + safeId + '">';
					if ( setting.options ) {
						$.each( setting.options, function( optValue, optLabel ) {
							var selected = value === optValue ? ' selected' : '';
							html += '<option value="' + escAttr( optValue ) + '"' + selected + '>' + escHtml( optLabel ) + '</option>';
						} );
					}
					html += '</select>';
					break;

				case 'number':
					html += '<input type="number" id="sndp-config-' + safeId + '" name="' + safeId + '" value="' + safeValue + '" class="small-text">';
					break;

				default:
					html += '<input type="text" id="sndp-config-' + safeId + '" name="' + safeId + '" value="' + safeValue + '" class="regular-text">';
			}

			if ( setting.description ) {
				html += '<p class="description">' + escHtml( setting.description ) + '</p>';
			}

			html += '</div>';
			return html;
		},

		/**
		 * Save configuration.
		 */
		saveConfiguration: function( e ) {
			e.preventDefault();

			var $form = $( this );
			var snippetId = $( '#sndp-configure-snippet-id' ).val();
			var config = {};

			$form.find( 'input, textarea, select' ).each( function() {
				var name = $( this ).attr( 'name' );
				if ( name && name !== 'snippet_id' ) {
					config[ name ] = $( this ).val();
				}
			} );

			$form.find( 'button[type="submit"]' ).prop( 'disabled', true ).text( sndp_admin.strings.saving );

			$.ajax( {
				url: sndp_admin.ajax_url,
				type: 'POST',
				data: {
					action: 'sndp_save_snippet_config',
					nonce: sndp_admin.nonce,
					snippet_id: snippetId,
					config: config
				},
				success: function( response ) {
					if ( response.success ) {
						SNDP_Admin.closeModal();
					} else {
						SNDP_Admin.showNotice( response.data.message || sndp_admin.strings.error );
					}
				},
				error: function() {
					SNDP_Admin.showNotice( sndp_admin.strings.error );
				},
				complete: function() {
					$form.find( 'button[type="submit"]' ).prop( 'disabled', false ).text( sndp_admin.strings.save_config );
				}
			} );
		},

		/**
		 * Reset configuration to defaults.
		 */
		resetConfiguration: function( e ) {
			e.preventDefault();
			$( '#sndp-configure-fields input, #sndp-configure-fields textarea' ).val( '' );
		},

		/**
		 * Copy library snippet to custom snippets.
		 */
		copyToCustom: function( e ) {
			e.preventDefault();
			e.stopPropagation();

			var $btn      = $( this );
			var snippetId = $btn.data( 'snippet-id' );
			var title     = $btn.closest( '.sndp-snippet-card' ).find( '.sndp-snippet-title' ).text().trim() || 'this snippet';

			SNDP_Admin.confirmModal( {
				icon: 'admin-page',
				type: 'info',
				title: sndp_admin.strings.copy_confirm_title || 'Copy to My Snippets',
				message: '<p>' + ( sndp_admin.strings.copy_confirm_desc || 'A copy of "%s" will be added to your custom snippets as inactive.').replace( '%s', '<strong>' + $( '<span>' ).text( title ).html() + '</strong>' ) + '</p>',
				buttons: [
					{ label: sndp_admin.strings.cancel, class: '', id: 'sndp-cm-cancel' },
					{ label: sndp_admin.strings.copy_btn || 'Copy Snippet', class: 'button-primary', id: 'sndp-cm-confirm' }
				],
				handlers: {
					'sndp-cm-cancel': function() { SNDP_Admin.closeConfirm(); },
					'sndp-cm-confirm': function( $cfBtn ) {
						$cfBtn.prop( 'disabled', true ).text( sndp_admin.strings.copying || 'Copying...' );
						SNDP_Admin.doCopy( snippetId, false );
					}
				}
			} );
		},

		doCopy: function( snippetId, force ) {
			$.ajax( {
				url: sndp_admin.ajax_url,
				type: 'POST',
				data: {
					action: 'sndp_copy_to_custom',
					nonce: sndp_admin.nonce,
					snippet_id: snippetId,
					force: force ? 'true' : 'false'
				},
				success: function( response ) {
					if ( response.success ) {
						SNDP_Admin.closeConfirm();
						SNDP_Admin.confirmModal( {
							icon: 'yes-alt',
							type: 'success',
							title: sndp_admin.strings.copied,
							message: '<p>' + sndp_admin.strings.copy_success_desc + '</p>',
							buttons: [
								{ label: sndp_admin.strings.copy_stay, class: '', id: 'sndp-cm-cancel' },
								{ label: sndp_admin.strings.copy_edit, class: 'button-primary', id: 'sndp-cm-confirm' }
							],
							handlers: {
								'sndp-cm-cancel': function() { SNDP_Admin.closeConfirm(); },
								'sndp-cm-confirm': function() {
									window.location.href = response.data.edit_url;
								}
							}
						} );
					} else if ( response.data && response.data.code === 'duplicate' ) {
						SNDP_Admin.closeConfirm();
						SNDP_Admin.confirmModal( {
							icon: 'warning',
							type: 'warning',
							title: sndp_admin.strings.copy_exists_title || 'Already Copied',
							message: '<p>' + ( sndp_admin.strings.copy_exists_desc || 'This snippet already exists in your custom snippets as "%s". Would you like to copy it again or edit the existing one?' ).replace( '%s', '<strong>' + $( '<span>' ).text( response.data.title ).html() + '</strong>' ) + '</p>',
							buttons: [
								{ label: sndp_admin.strings.cancel, class: '', id: 'sndp-cm-cancel' },
								{ label: sndp_admin.strings.copy_again || 'Copy Again', class: '', id: 'sndp-cm-again' },
								{ label: sndp_admin.strings.copy_edit_existing || 'Edit Existing', class: 'button-primary', id: 'sndp-cm-edit' }
							],
							handlers: {
								'sndp-cm-cancel': function() { SNDP_Admin.closeConfirm(); },
								'sndp-cm-again': function( $btn ) {
									$btn.prop( 'disabled', true ).text( sndp_admin.strings.copying || 'Copying...' );
									SNDP_Admin.doCopy( snippetId, true );
								},
								'sndp-cm-edit': function() {
									window.location.href = response.data.edit_url;
								}
							}
						} );
					} else {
						SNDP_Admin.closeConfirm();
						SNDP_Admin.showNotice( response.data.message || sndp_admin.strings.error );
					}
				},
				error: function() {
					SNDP_Admin.closeConfirm();
					SNDP_Admin.showNotice( sndp_admin.strings.error );
				}
			} );
		},

		/**
		 * Toggle custom snippet.
		 */
		toggleCustomSnippet: function() {
			var $toggle = $( this );
			var snippetId = $toggle.data( 'snippet-id' );
			var $row = $toggle.closest( 'tr' );

			$toggle.prop( 'disabled', true );

			$.ajax( {
				url: sndp_admin.ajax_url,
				type: 'POST',
				data: {
					action: 'sndp_toggle_custom_snippet',
					nonce: sndp_admin.nonce,
					snippet_id: snippetId
				},
				success: function( response ) {
					if ( response.success ) {
						if ( 'active' === response.data.status ) {
							$row.addClass( 'sndp-snippet-active' );
							$toggle.prop( 'checked', true );
						} else {
							$row.removeClass( 'sndp-snippet-active' );
							$toggle.prop( 'checked', false );
						}
						SNDP_Admin.showNotice( response.data.message, 'success' );

						// Show conflict warnings (non-blocking) after activation.
						if ( response.data.conflict_warnings && response.data.conflict_warnings.length ) {
							var conflictMsg = sndp_admin.strings.conflict_enable_warn + '\n' + response.data.conflict_warnings.join( '\n' ) + '\n\n' + sndp_admin.strings.conflict_proceed;
							SNDP_Admin.showNotice( conflictMsg, 'warning' );
						}
					} else {
						SNDP_Admin.showNotice( response.data.message || sndp_admin.strings.error );
						$toggle.prop( 'checked', ! $toggle.prop( 'checked' ) );
					}
				},
				error: function() {
					SNDP_Admin.showNotice( sndp_admin.strings.error );
					$toggle.prop( 'checked', ! $toggle.prop( 'checked' ) );
				},
				complete: function() {
					$toggle.prop( 'disabled', false );
				}
			} );
		},

		/**
		 * Delete custom snippet.
		 */
		deleteCustomSnippet: function( e ) {
			e.preventDefault();

			var $link     = $( this );
			var snippetId = $link.data( 'snippet-id' );
			var $row      = $link.closest( 'tr' );
			var title     = $row.find( '.column-title a' ).first().text().trim() || sndp_admin.strings.snippet || 'snippet';

			SNDP_Admin.confirmModal( {
				icon: 'trash',
				type: 'danger',
				title: sndp_admin.strings.delete_title || 'Delete Snippet',
				message: '<p>' + ( sndp_admin.strings.delete_desc || 'Are you sure you want to delete "%s"? This action cannot be undone.' ).replace( '%s', '<strong>' + $( '<span>' ).text( title ).html() + '</strong>' ) + '</p>',
				buttons: [
					{ label: sndp_admin.strings.cancel, class: '', id: 'sndp-cm-cancel' },
					{ label: sndp_admin.strings.delete_btn || 'Delete', class: 'button-link-delete', id: 'sndp-cm-confirm' }
				],
				handlers: {
					'sndp-cm-cancel': function() { SNDP_Admin.closeConfirm(); },
					'sndp-cm-confirm': function( $btn ) {
						$btn.prop( 'disabled', true ).text( sndp_admin.strings.deleting || 'Deleting...' );
						$.ajax( {
							url: sndp_admin.ajax_url,
							type: 'POST',
							data: {
								action: 'sndp_delete_custom_snippet',
								nonce: sndp_admin.nonce,
								snippet_id: snippetId
							},
							success: function( response ) {
								SNDP_Admin.closeConfirm();
								if ( response.success ) {
									$row.fadeOut( function() { $( this ).remove(); } );
								} else {
									SNDP_Admin.showNotice( response.data.message || sndp_admin.strings.error );
								}
							},
							error: function() {
								SNDP_Admin.closeConfirm();
								SNDP_Admin.showNotice( sndp_admin.strings.error );
							}
						} );
					}
				}
			} );
		},

		/**
		 * Duplicate custom snippet.
		 */
		duplicateCustomSnippet: function( e ) {
			e.preventDefault();

			var $link = $( this );
			var snippetId = $link.data( 'snippet-id' );

			$.ajax( {
				url: sndp_admin.ajax_url,
				type: 'POST',
				data: {
					action: 'sndp_duplicate_custom_snippet',
					nonce: sndp_admin.nonce,
					snippet_id: snippetId
				},
				success: function( response ) {
					if ( response.success ) {
						location.reload();
					} else {
						SNDP_Admin.showNotice( response.data.message || sndp_admin.strings.error );
					}
				},
				error: function() {
					SNDP_Admin.showNotice( sndp_admin.strings.error );
				}
			} );
		},

		/**
		 * Save custom snippet.
		 */
		saveCustomSnippet: function( e ) {
			e.preventDefault();

			var $form = $( this );
			var $submitBtn = $form.find( '.sndp-save-snippet' );

			// Get code from editor if available.
			var code = '';
			if ( SNDP_Admin.editor ) {
				code = SNDP_Admin.editor.codemirror.getValue();
			} else {
				code = $( '#sndp-snippet-code' ).val();
			}

			// Get selected post types.
			var postTypes = [];
			$( 'input[name="post_types[]"]:checked' ).each( function() {
				postTypes.push( $( this ).val() );
			} );

			// Get selected taxonomies.
			var taxonomies = [];
			$( '.sndp-taxonomy-checklist input[name="taxonomies[]"]:checked' ).each( function() {
				taxonomies.push( $( this ).val() );
			} );

			$submitBtn.prop( 'disabled', true ).text( sndp_admin.strings.saving );

			$.ajax( {
				url: sndp_admin.ajax_url,
				type: 'POST',
				data: {
					action: 'sndp_save_custom_snippet',
					nonce: sndp_admin.nonce,
					id: $( '#sndp-snippet-id' ).val(),
					title: $( '#sndp-snippet-title' ).val(),
					description: $( '#sndp-snippet-description' ).val(),
					code: code,
					code_type: $( 'input[name="code_type"]:checked' ).val(),
					status: $( 'input[name="status"]' ).is( ':checked' ) ? 'active' : 'inactive',
					hook: $( '#sndp-snippet-hook' ).val(),
					priority: $( '#sndp-snippet-priority' ).val(),
					location: $( '#sndp-snippet-location' ).val(),
					user_cond: $( '#sndp-snippet-user-cond' ).val(),
					'post_types[]': postTypes,
					page_ids: $( '#sndp-snippet-page-ids' ).val(),
					url_patterns: $( '#sndp-snippet-url-patterns' ).val(),
					'taxonomies[]': taxonomies,
					schedule_start: SNDP_Admin.getScheduleValue( 'start' ),
					schedule_end: SNDP_Admin.getScheduleValue( 'end' ),
					tags: $( '#sndp-snippet-tags' ).val(),
					conditional_rules: $( '#sndp-conditional-rules' ).val(),
					shortcode_name: $( '#sndp-shortcode-name' ).val(),
					insert_paragraph: $( '#sndp-insert-paragraph' ).val()
				},
				success: function( response ) {
					if ( response.success ) {
						// Update ID if new snippet.
						if ( ! $( '#sndp-snippet-id' ).val() ) {
							$( '#sndp-snippet-id' ).val( response.data.snippet_id );
							// Update URL without reload.
							var newUrl = window.location.href.split( '?' )[0] + '?page=snipdrop-add&id=' + response.data.snippet_id;
							window.history.replaceState( {}, '', newUrl );

							// Update shortcode display.
							$( '#sndp-shortcode-code' ).text( '[snipdrop id="' + response.data.snippet_id + '"]' );
						}
						$submitBtn.text( sndp_admin.strings.saved );
						SNDP_Admin.formDirty = false;
						SNDP_Admin.formSaving = false;
						setTimeout( function() {
							$submitBtn.text( sndp_admin.strings.update_snippet );
						}, 1500 );

						// Show warnings if any.
						$( '.sndp-code-warnings' ).remove();
						$( '.sndp-compat-warnings' ).remove();
						if ( response.data.warnings_html ) {
							$( '.sndp-code-field' ).after( response.data.warnings_html );
						}

						// Show compatibility warnings if any.
						if ( response.data.compat_warnings && response.data.compat_warnings.length ) {
							var html = '<div class="sndp-compat-warnings notice notice-warning inline"><p><strong>' + escHtml( sndp_admin.strings.compat_code_warn ) + '</strong></p><ul>';
							for ( var i = 0; i < response.data.compat_warnings.length; i++ ) {
								html += '<li>' + escHtml( response.data.compat_warnings[ i ] ) + '</li>';
							}
							html += '</ul></div>';
							$( '.sndp-code-field' ).after( html );
						}

						// Show shortcode hint if location is shortcode.
						SNDP_Admin.toggleConditionalOptions();
					} else {
						SNDP_Admin.showNotice( response.data.message || sndp_admin.strings.error );
					}
				},
				error: function() {
					SNDP_Admin.showNotice( sndp_admin.strings.error );
				},
				complete: function() {
					$submitBtn.prop( 'disabled', false );
				}
			} );
		},

		/**
		 * Save and activate snippet.
		 */
		saveAndActivate: function( e ) {
			e.preventDefault();
			$( 'input[name="status"]' ).prop( 'checked', true );
			$( '#sndp-snippet-form' ).submit();
		},

		/**
		 * Close modal.
		 */
		copyCode: function() {
			var code = $( '#sndp-modal-code' ).text();
			var $btn = $( this );
			var originalText = $btn.html();

			navigator.clipboard.writeText( code ).then( function() {
				$btn.html( '<span class="dashicons dashicons-yes"></span> Copied!' );
				setTimeout( function() {
					$btn.html( originalText );
				}, 2000 );
			} ).catch( function() {
				// Fallback for older browsers.
				var $temp = $( '<textarea>' );
				$( 'body' ).append( $temp );
				$temp.val( code ).select();
				document.execCommand( 'copy' );
				$temp.remove();
				$btn.html( '<span class="dashicons dashicons-yes"></span> Copied!' );
				setTimeout( function() {
					$btn.html( originalText );
				}, 2000 );
			} );
		},

		/**
		 * Copy shortcode to clipboard.
		 */
		copyShortcode: function() {
			var shortcode = $( '#sndp-shortcode-code' ).text();
			var $btn = $( this );

			navigator.clipboard.writeText( shortcode ).then( function() {
				$btn.find( '.dashicons' ).removeClass( 'dashicons-clipboard' ).addClass( 'dashicons-yes' );
				setTimeout( function() {
					$btn.find( '.dashicons' ).removeClass( 'dashicons-yes' ).addClass( 'dashicons-clipboard' );
				}, 2000 );
			} ).catch( function() {
				// Fallback for older browsers.
				var $temp = $( '<textarea>' );
				$( 'body' ).append( $temp );
				$temp.val( shortcode ).select();
				document.execCommand( 'copy' );
				$temp.remove();
				$btn.find( '.dashicons' ).removeClass( 'dashicons-clipboard' ).addClass( 'dashicons-yes' );
				setTimeout( function() {
					$btn.find( '.dashicons' ).removeClass( 'dashicons-yes' ).addClass( 'dashicons-clipboard' );
				}, 2000 );
			} );
		},

		handleSelectAll: function() {
			var checked = $( this ).prop( 'checked' );
			$( '.sndp-bulk-check' ).prop( 'checked', checked );
			SNDP_Admin.updateBulkApplyState();
		},

		handleBulkCheckChange: function() {
			var total   = $( '.sndp-bulk-check' ).length;
			var checked = $( '.sndp-bulk-check:checked' ).length;
			$( '#sndp-select-all' ).prop( 'checked', total === checked && total > 0 );
			SNDP_Admin.updateBulkApplyState();
		},

		updateBulkApplyState: function() {
			var hasChecked = $( '.sndp-bulk-check:checked' ).length > 0;
			$( '#sndp-bulk-apply' ).prop( 'disabled', ! hasChecked );
		},

		handleBulkApply: function( e ) {
			e.preventDefault();
			var action = $( '#sndp-bulk-action' ).val();
			if ( ! action ) {
				SNDP_Admin.showNotice( sndp_admin.strings.error );
				return;
			}

			var ids = [];
			$( '.sndp-bulk-check:checked' ).each( function() {
				ids.push( $( this ).val() );
			} );

			if ( ! ids.length ) {
				return;
			}

			var self = this;

			if ( 'delete' === action ) {
				SNDP_Admin.confirmModal( {
					icon: 'trash',
					type: 'danger',
					title: sndp_admin.strings.bulk_delete_title || 'Delete Snippets',
					message: '<p>' + ( sndp_admin.strings.bulk_delete_desc || 'Are you sure you want to delete %d snippet(s)? This action cannot be undone.' ).replace( '%d', ids.length ) + '</p>',
					buttons: [
						{ label: sndp_admin.strings.cancel, class: '', id: 'sndp-cm-cancel' },
						{ label: sndp_admin.strings.delete_btn || 'Delete', class: 'button-link-delete', id: 'sndp-cm-confirm' }
					],
					handlers: {
						'sndp-cm-cancel': function() { SNDP_Admin.closeConfirm(); },
						'sndp-cm-confirm': function( $cfBtn ) {
							$cfBtn.prop( 'disabled', true ).text( sndp_admin.strings.deleting || 'Deleting...' );
							SNDP_Admin.closeConfirm();
							SNDP_Admin.executeBulk( action, ids );
						}
					}
				} );
				return;
			}

			SNDP_Admin.executeBulk( action, ids );
		},

		executeBulk: function( action, ids ) {
			var $btn = $( '#sndp-bulk-apply' );
			$btn.prop( 'disabled', true );

			$.ajax( {
				url: sndp_admin.ajax_url,
				type: 'POST',
				data: {
					action: 'sndp_bulk_action',
					nonce: sndp_admin.nonce,
					bulk_action: action,
					snippet_ids: ids
				},
				success: function( response ) {
					if ( response.success ) {
						SNDP_Admin.showNotice( response.data.message, 'success' );
						setTimeout( function() { location.reload(); }, 1000 );
					} else {
						SNDP_Admin.showNotice( response.data.message || sndp_admin.strings.error );
					}
				},
				error: function() {
					SNDP_Admin.showNotice( sndp_admin.strings.error );
				},
				complete: function() {
					$btn.prop( 'disabled', false );
				}
			} );
		},

		filterCustomSnippets: function() {
			var query = $( this ).val().toLowerCase();
			$( '.sndp-custom-snippets-table tbody tr' ).each( function() {
				var $row = $( this );
				var title = $row.find( '.column-title' ).text().toLowerCase();
				var type  = $row.find( '.column-type' ).text().toLowerCase();
				var tags  = $row.find( '.column-tags' ).text().toLowerCase();
				$row.toggle(
					title.indexOf( query ) !== -1 ||
					type.indexOf( query ) !== -1 ||
					tags.indexOf( query ) !== -1
				);
			} );
		},

		pageSearchTimer: null,

		handlePageSearch: function() {
			var query = $( this ).val().trim();
			var $results = $( '#sndp-page-search-results' );

			clearTimeout( SNDP_Admin.pageSearchTimer );

			if ( query.length < 2 ) {
				$results.empty().hide();
				return;
			}

			SNDP_Admin.pageSearchTimer = setTimeout( function() {
				$.ajax( {
					url: sndp_admin.ajax_url,
					type: 'POST',
					data: {
						action: 'sndp_search_posts',
						nonce: sndp_admin.nonce,
						search: query
					},
					success: function( response ) {
						$results.empty();
						if ( ! response.success || ! response.data.results.length ) {
							$results.html( '<div class="sndp-page-result-empty">' + escHtml( sndp_admin.strings.no_results ) + '</div>' ).show();
							return;
						}

						var selectedIds = ( $( '#sndp-snippet-page-ids' ).val() || '' ).split( ',' ).filter( Boolean );

						$.each( response.data.results, function( i, item ) {
							if ( selectedIds.indexOf( String( item.id ) ) !== -1 ) {
								return;
							}
							$results.append(
								'<div class="sndp-page-result" data-id="' + escAttr( item.id ) + '" data-title="' + escAttr( item.title ) + '" data-type="' + escAttr( item.post_type ) + '">' +
								escHtml( item.title ) +
								' <span class="sndp-page-result-type">' + escHtml( item.post_type ) + ' #' + item.id + '</span>' +
								'</div>'
							);
						} );

						if ( ! $results.children( '.sndp-page-result' ).length ) {
							$results.html( '<div class="sndp-page-result-empty">' + escHtml( sndp_admin.strings.no_results ) + '</div>' );
						}

						$results.show();
					}
				} );
			}, 300 );
		},

		handlePageSelect: function() {
			var $item = $( this );
			var id    = $item.data( 'id' );
			var title = $item.data( 'title' );
			var type  = $item.data( 'type' );

			var tag = '<span class="sndp-page-tag" data-id="' + escAttr( id ) + '">' +
				escHtml( title ) +
				' <span class="sndp-page-tag-type">' + escHtml( type ) + '</span>' +
				'<button type="button" class="sndp-page-tag-remove">&times;</button>' +
				'</span>';

			$( '#sndp-selected-pages' ).append( tag );
			SNDP_Admin.syncPageIds();

			$( '#sndp-page-search' ).val( '' );
			$( '#sndp-page-search-results' ).empty().hide();
		},

		handlePageRemove: function( e ) {
			e.preventDefault();
			$( this ).closest( '.sndp-page-tag' ).remove();
			SNDP_Admin.syncPageIds();
		},

		syncPageIds: function() {
			var ids = [];
			$( '#sndp-selected-pages .sndp-page-tag' ).each( function() {
				ids.push( $( this ).data( 'id' ) );
			} );
			$( '#sndp-snippet-page-ids' ).val( ids.join( ',' ) );
		},

		handleRestoreRevision: function( e ) {
			e.preventDefault();
			var $btn = $( this );
			var snippetId = $btn.data( 'snippet-id' );
			var revisionIndex = $btn.data( 'revision-index' );

			if ( ! confirm( sndp_admin.strings.confirm_restore || 'Restore this revision? Current code will be saved as a new revision.' ) ) {
				return;
			}

			$btn.prop( 'disabled', true );

			$.ajax( {
				url: sndp_admin.ajax_url,
				type: 'POST',
				data: {
					action: 'sndp_restore_revision',
					nonce: sndp_admin.nonce,
					snippet_id: snippetId,
					revision_index: revisionIndex
				},
				success: function( response ) {
					if ( response.success ) {
						SNDP_Admin.showNotice( response.data.message, 'success' );
						setTimeout( function() { location.reload(); }, 1000 );
					} else {
						SNDP_Admin.showNotice( response.data.message || sndp_admin.strings.error );
					}
				},
				error: function() {
					SNDP_Admin.showNotice( sndp_admin.strings.error );
				},
				complete: function() {
					$btn.prop( 'disabled', false );
				}
			} );
		},

		handleRestoreGlobalRevision: function( e ) {
			e.preventDefault();
			var $btn = $( this );
			var revisionIndex = $btn.data( 'revision-index' );

			if ( ! confirm( sndp_admin.strings.confirm_restore || 'Restore this revision? Current scripts will be saved as a new revision.' ) ) {
				return;
			}

			$btn.prop( 'disabled', true );

			$.ajax( {
				url: sndp_admin.ajax_url,
				type: 'POST',
				data: {
					action: 'sndp_restore_global_revision',
					nonce: sndp_admin.nonce,
					revision_index: revisionIndex
				},
				success: function( response ) {
					if ( response.success ) {
						SNDP_Admin.showNotice( response.data.message, 'success' );
						setTimeout( function() { location.reload(); }, 1000 );
					} else {
						SNDP_Admin.showNotice( response.data.message || sndp_admin.strings.error );
					}
				},
				error: function() {
					SNDP_Admin.showNotice( sndp_admin.strings.error );
				},
				complete: function() {
					$btn.prop( 'disabled', false );
				}
			} );
		},

		handleViewToggle: function( e ) {
			e.preventDefault();
			var $btn = $( this );
			var view = $btn.data( 'view' );

			$( '.sndp-view-btn' ).removeClass( 'active' );
			$btn.addClass( 'active' );

			var $grid = $( '#sndp-snippets-grid' );
			if ( 'list' === view ) {
				$grid.addClass( 'sndp-list-view' );
			} else {
				$grid.removeClass( 'sndp-list-view' );
			}

			localStorage.setItem( 'sndp_view_mode', view );
		},

		showImportForm: function( e ) {
			e.preventDefault();
			$( '#sndp-import-form' ).slideDown( 200 );
		},

		hideImportForm: function( e ) {
			e.preventDefault();
			$( '#sndp-import-form' ).slideUp( 200 );
			$( '#sndp-import-file' ).val( '' );
			$( '#sndp-import-submit' ).prop( 'disabled', true );
		},

		handleImportFileSelect: function() {
			var hasFile = $( this ).val().length > 0;
			$( '#sndp-import-submit' ).prop( 'disabled', ! hasFile );
		},

		handleImportSubmit: function( e ) {
			e.preventDefault();

			var $form    = $( this );
			var $submit  = $form.find( '#sndp-import-submit' );
			var fileEl   = document.getElementById( 'sndp-import-file' );

			if ( ! fileEl.files || ! fileEl.files[0] ) {
				return;
			}

			var formData = new FormData();
			formData.append( 'action', 'sndp_import_snippets' );
			formData.append( 'nonce', sndp_admin.nonce );
			formData.append( 'import_file', fileEl.files[0] );

			$submit.prop( 'disabled', true ).text( sndp_admin.strings.saving );

			$.ajax( {
				url: sndp_admin.ajax_url,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success: function( response ) {
					if ( response.success ) {
						SNDP_Admin.showNotice( response.data.message, 'success' );
						setTimeout( function() {
							location.reload();
						}, 1500 );
					} else {
						SNDP_Admin.showNotice( response.data.message || sndp_admin.strings.error );
					}
				},
				error: function() {
					SNDP_Admin.showNotice( sndp_admin.strings.error );
				},
				complete: function() {
					$submit.prop( 'disabled', false ).text( sndp_admin.strings.import_submit );
				}
			} );
		},

		/**
		 * Line-based diff using longest common subsequence.
		 * Returns an array of { type: 'added'|'removed'|'context', line: string }.
		 */
		computeDiff: function( oldLines, newLines ) {
			var m = oldLines.length;
			var n = newLines.length;
			var dp = [];
			var i, j;

			for ( i = 0; i <= m; i++ ) {
				dp[ i ] = [];
				for ( j = 0; j <= n; j++ ) {
					if ( 0 === i || 0 === j ) {
						dp[ i ][ j ] = 0;
					} else if ( oldLines[ i - 1 ] === newLines[ j - 1 ] ) {
						dp[ i ][ j ] = dp[ i - 1 ][ j - 1 ] + 1;
					} else {
						dp[ i ][ j ] = Math.max( dp[ i - 1 ][ j ], dp[ i ][ j - 1 ] );
					}
				}
			}

			var result = [];
			i = m;
			j = n;

			while ( i > 0 || j > 0 ) {
				if ( i > 0 && j > 0 && oldLines[ i - 1 ] === newLines[ j - 1 ] ) {
					result.unshift( { type: 'context', line: oldLines[ i - 1 ] } );
					i--;
					j--;
				} else if ( j > 0 && ( 0 === i || dp[ i ][ j - 1 ] >= dp[ i - 1 ][ j ] ) ) {
					result.unshift( { type: 'added', line: newLines[ j - 1 ] } );
					j--;
				} else {
					result.unshift( { type: 'removed', line: oldLines[ i - 1 ] } );
					i--;
				}
			}

			return result;
		},

		/**
		 * Collapse unchanged lines, keeping context lines around changes.
		 */
		collapseContext: function( diff, contextSize ) {
			contextSize = contextSize || 2;
			var keep = [];
			var i, j;

			for ( i = 0; i < diff.length; i++ ) {
				if ( 'context' !== diff[ i ].type ) {
					for ( j = Math.max( 0, i - contextSize ); j <= Math.min( diff.length - 1, i + contextSize ); j++ ) {
						keep[ j ] = true;
					}
				}
			}

			var collapsed = [];
			var skipping = false;

			for ( i = 0; i < diff.length; i++ ) {
				if ( keep[ i ] ) {
					skipping = false;
					collapsed.push( diff[ i ] );
				} else if ( ! skipping ) {
					skipping = true;
					collapsed.push( { type: 'separator', line: '' } );
				}
			}

			return collapsed;
		},

		handleViewDiff: function( e ) {
			e.preventDefault();
			var $btn = $( this );
			var revCode = atob( $btn.data( 'revision-code' ) );
			var revDate = $btn.data( 'revision-date' );

			var currentCode = '';
			if ( SNDP_Admin.codeEditor && SNDP_Admin.codeEditor.codemirror ) {
				currentCode = SNDP_Admin.codeEditor.codemirror.getValue();
			} else {
				currentCode = $( '#sndp-snippet-code' ).val() || '';
			}

			var oldLines = revCode.split( '\n' );
			var newLines = currentCode.split( '\n' );
			var diff = SNDP_Admin.computeDiff( oldLines, newLines );
			var collapsed = SNDP_Admin.collapseContext( diff, 2 );

			var html = '<table class="sndp-diff-table"><tbody>';
			var oldNum = 0;
			var newNum = 0;

			for ( var i = 0; i < collapsed.length; i++ ) {
				var entry = collapsed[ i ];

				if ( 'separator' === entry.type ) {
					html += '<tr class="sndp-diff-separator"><td class="sndp-diff-gutter" colspan="2"></td><td class="sndp-diff-line-content">···</td></tr>';
					continue;
				}

				if ( 'removed' === entry.type ) {
					oldNum++;
					html += '<tr class="sndp-diff-removed">';
					html += '<td class="sndp-diff-gutter sndp-diff-old-num">' + oldNum + '</td>';
					html += '<td class="sndp-diff-gutter sndp-diff-new-num"></td>';
					html += '<td class="sndp-diff-line-content"><span class="sndp-diff-prefix">−</span>' + escHtml( entry.line || ' ' ) + '</td>';
					html += '</tr>';
				} else if ( 'added' === entry.type ) {
					newNum++;
					html += '<tr class="sndp-diff-added">';
					html += '<td class="sndp-diff-gutter sndp-diff-old-num"></td>';
					html += '<td class="sndp-diff-gutter sndp-diff-new-num">' + newNum + '</td>';
					html += '<td class="sndp-diff-line-content"><span class="sndp-diff-prefix">+</span>' + escHtml( entry.line || ' ' ) + '</td>';
					html += '</tr>';
				} else {
					oldNum++;
					newNum++;
					html += '<tr class="sndp-diff-context">';
					html += '<td class="sndp-diff-gutter sndp-diff-old-num">' + oldNum + '</td>';
					html += '<td class="sndp-diff-gutter sndp-diff-new-num">' + newNum + '</td>';
					html += '<td class="sndp-diff-line-content"><span class="sndp-diff-prefix"> </span>' + escHtml( entry.line || ' ' ) + '</td>';
					html += '</tr>';
				}
			}

			html += '</tbody></table>';

			if ( 0 === diff.length || ( 1 === collapsed.length && 'separator' === collapsed[0].type ) ) {
				html = '<p class="sndp-diff-identical">' + ( sndp_admin.strings.diff_identical || 'No changes — the code is identical.' ) + '</p>';
			}

			var title = ( sndp_admin.strings.diff_title || 'Changes since %s' ).replace( '%s', escHtml( revDate ) );
			$( '#sndp-diff-modal-title' ).html( title );
			$( '#sndp-diff-output' ).html( html );
			$( '#sndp-diff-modal' ).addClass( 'sndp-modal-open' );
		},

		openModal: function( title, bodyHtml ) {
			$( '#sndp-diff-modal-title' ).html( title );
			$( '#sndp-diff-output' ).html( bodyHtml );
			$( '#sndp-diff-modal' ).addClass( 'sndp-modal-open' );
		},

		closeModal: function() {
			$( '.sndp-modal' ).removeClass( 'sndp-modal-open' );
		},

		/**
		 * Show a polished confirmation modal.
		 *
		 * @param {Object} opts
		 * @param {string} opts.icon      Dashicon name (e.g. 'visibility', 'warning', 'shield')
		 * @param {string} opts.type      Icon color class: 'info', 'warning', 'danger', 'success'
		 * @param {string} opts.title     Modal title
		 * @param {string} opts.message   HTML body text
		 * @param {Array}  opts.buttons   Array of { label, class, id } objects
		 * @param {Object} opts.handlers  Map of button id → click callback
		 */
		confirmModal: function( opts ) {
			var iconClass = 'sndp-confirm-icon--' + ( opts.type || 'info' );
			$( '#sndp-confirm-icon' )
				.attr( 'class', 'sndp-confirm-icon ' + iconClass )
				.html( '<span class="dashicons dashicons-' + ( opts.icon || 'info' ) + '"></span>' );

			$( '#sndp-confirm-title' ).text( opts.title || '' );
			$( '#sndp-confirm-body' ).html( opts.message || '' );

			var btns = '';
			( opts.buttons || [] ).forEach( function( btn ) {
				btns += '<button type="button" class="button ' + ( btn.class || '' ) + '" id="' + btn.id + '">' + btn.label + '</button>';
			} );
			$( '#sndp-confirm-actions' ).html( btns );

			$( '#sndp-confirm-modal' ).addClass( 'sndp-modal-open' );

			// Bind handlers.
			if ( opts.handlers ) {
				$.each( opts.handlers, function( id, fn ) {
					$( '#' + id ).on( 'click', function() {
						fn( $( this ) );
					} );
				} );
			}
		},

		closeConfirm: function() {
			$( '#sndp-confirm-modal' ).removeClass( 'sndp-modal-open' );
		},

		saveGlobalScripts: function( e ) {
			e.preventDefault();
			var $btn = $( '#sndp-save-global-scripts' );
			var $spinner = $( '#sndp-global-spinner' );

			$btn.prop( 'disabled', true );
			$spinner.addClass( 'is-active' );

			var editors = {};
			$( '.sndp-global-editor' ).each( function() {
				var id = $( this ).attr( 'id' );
				var cmInstance = $( this ).next( '.CodeMirror' );
				if ( cmInstance.length && cmInstance[0].CodeMirror ) {
					editors[ $( this ).attr( 'name' ) ] = cmInstance[0].CodeMirror.getValue();
				} else {
					editors[ $( this ).attr( 'name' ) ] = $( this ).val();
				}
			} );

			$.ajax( {
				url: sndp_admin.ajax_url,
				type: 'POST',
				data: {
					action: 'sndp_save_global_scripts',
					nonce: sndp_admin.nonce,
					header: editors.header || '',
					body_open: editors.body_open || '',
					footer: editors.footer || ''
				},
				success: function( response ) {
					if ( response.success ) {
						SNDP_Admin.showNotice( response.data.message, 'success' );
					} else {
						SNDP_Admin.showNotice( response.data.message || sndp_admin.strings.error );
					}
				},
				error: function() {
					SNDP_Admin.showNotice( sndp_admin.strings.error );
				},
				complete: function() {
					$btn.prop( 'disabled', false );
					$spinner.removeClass( 'is-active' );
				}
			} );
		}
	};

	/**
	 * Conditional Logic Rule Builder.
	 */
	var SNDP_RuleBuilder = {

		$panel: null,
		$body: null,
		conditionTypes: [],
		categories: {},
		groupCounter: 0,
		ruleCounter: 0,

		init: function() {
			this.$panel = $( '#sndp-conditions-panel' );
			this.$body = this.$panel.find( '.sndp-conditions-body' );

			if ( ! this.$panel.length ) {
				return;
			}

			this.conditionTypes = sndp_admin.condition_types || [];
			this.categories = sndp_admin.condition_categories || {};

			this.bindEvents();
			this.loadExistingRules();
		},

		bindEvents: function() {
			var self = this;

			this.$panel.on( 'click', '.sndp-rb-add-rule', function() {
				var $group = $( this ).closest( '.sndp-rb-group' );
				self.addRule( $group );
			} );

			this.$panel.on( 'click', '.sndp-rb-add-group', function() {
				self.addGroup();
			} );

			this.$panel.on( 'click', '.sndp-rb-remove-rule', function() {
				var $rule = $( this ).closest( '.sndp-rb-rule' );
				var $group = $rule.closest( '.sndp-rb-group' );
				var $sw = $rule.find( '.sndp-rb-value-select' );
				if ( $sw.length && ( $.fn.selectWoo || $.fn.select2 ) ) {
					try {
						if ( $.fn.selectWoo ) { $sw.selectWoo( 'destroy' ); } else { $sw.select2( 'destroy' ); }
					} catch ( e ) {}
				}
				$rule.slideUp( 150, function() {
					$( this ).remove();
					if ( 0 === $group.find( '.sndp-rb-rule' ).length ) {
						$group.slideUp( 150, function() {
							$( this ).remove();
							self.updateGroupLabels();
							self.serializeRules();
						} );
					} else {
						self.serializeRules();
					}
				} );
			} );

			this.$panel.on( 'click', '.sndp-rb-remove-group', function() {
				$( this ).closest( '.sndp-rb-group' ).slideUp( 150, function() {
					$( this ).remove();
					self.updateGroupLabels();
					self.serializeRules();
				} );
			} );

			this.$panel.on( 'change', '.sndp-rb-type-select', function() {
				var $rule = $( this ).closest( '.sndp-rb-rule' );
				self.onTypeChange( $rule, $( this ).val() );
				self.serializeRules();
			} );

			this.$panel.on( 'change', '.sndp-rb-operator-select, .sndp-rb-value-input, .sndp-rb-value-select', function() {
				self.serializeRules();
			} );

			this.$panel.on( 'change', '.sndp-rb-value-checkbox', function() {
				self.serializeRules();
			} );

			this.$panel.on( 'change', '.sndp-rb-group-match', function() {
				self.serializeRules();
			} );

			this.$panel.on( 'change', '.sndp-rb-global-match', function() {
				self.serializeRules();
			} );
		},

		loadExistingRules: function() {
			var rulesJson = $( '#sndp-conditional-rules' ).val();
			if ( ! rulesJson ) {
				this.renderEmptyState();
				return;
			}

			try {
				var rules = JSON.parse( rulesJson );
				if ( rules && rules.groups && rules.groups.length ) {
					this.renderRules( rules );
				} else {
					this.renderEmptyState();
				}
			} catch ( e ) {
				this.renderEmptyState();
			}
		},

		renderEmptyState: function() {
			var html = this.buildBuilderHTML( null );
			this.$body.html( html );
		},

		renderRules: function( rules ) {
			var html = this.buildBuilderHTML( rules );
			this.$body.html( html );

			var self = this;
			if ( rules.groups ) {
				for ( var g = 0; g < rules.groups.length; g++ ) {
					var group = rules.groups[ g ];
					if ( group.rules ) {
						for ( var r = 0; r < group.rules.length; r++ ) {
							var rule = group.rules[ r ];
							var $ruleEl = this.$body.find( '.sndp-rb-rule[data-rule-index="' + g + '-' + r + '"]' );
							if ( $ruleEl.length && rule.type ) {
								self.onTypeChange( $ruleEl, rule.type, rule.operator, rule.value );
							}
						}
					}
				}
			}
		},

		buildBuilderHTML: function( rules ) {
			var s = sndp_admin.strings;
			var html = '';

			html += '<div class="sndp-rb-builder">';

			if ( rules && rules.groups && rules.groups.length > 1 ) {
				html += '<div class="sndp-rb-global-bar">';
				html += '<label>' + escHtml( s.cl_groups_match ) + ' </label>';
				html += '<select class="sndp-rb-global-match">';
				html += '<option value="all"' + ( rules.match === 'all' ? ' selected' : '' ) + '>' + escHtml( s.cl_match_all ) + '</option>';
				html += '<option value="any"' + ( rules.match === 'any' ? ' selected' : '' ) + '>' + escHtml( s.cl_match_any ) + '</option>';
				html += '</select>';
				html += '</div>';
			}

			html += '<div class="sndp-rb-groups">';

			if ( rules && rules.groups && rules.groups.length ) {
				for ( var g = 0; g < rules.groups.length; g++ ) {
					html += this.buildGroupHTML( rules.groups[ g ], g );
				}
			} else {
				html += this.buildGroupHTML( null, 0 );
			}

			html += '</div>';

			html += '<div class="sndp-rb-actions">';
			html += '<button type="button" class="button sndp-rb-add-group">';
			html += '<span class="dashicons dashicons-plus-alt2"></span> ' + escHtml( s.cl_add_group );
			html += '</button>';
			html += '</div>';

			html += '</div>';
			return html;
		},

		buildGroupHTML: function( group, index ) {
			var s = sndp_admin.strings;
			var matchVal = group ? ( group.match || 'all' ) : 'all';
			this.groupCounter = Math.max( this.groupCounter, index + 1 );

			var html = '<div class="sndp-rb-group" data-group-index="' + index + '">';
			html += '<div class="sndp-rb-group-header">';
			html += '<span class="sndp-rb-group-label">' + escHtml( s.cl_group_label ) + ' ' + ( index + 1 ) + '</span>';
			html += '<select class="sndp-rb-group-match">';
			html += '<option value="all"' + ( matchVal === 'all' ? ' selected' : '' ) + '>' + escHtml( s.cl_match_all ) + '</option>';
			html += '<option value="any"' + ( matchVal === 'any' ? ' selected' : '' ) + '>' + escHtml( s.cl_match_any ) + '</option>';
			html += '</select>';
			html += '<button type="button" class="sndp-rb-remove-group" title="' + escAttr( s.cl_remove_group ) + '">';
			html += '<span class="dashicons dashicons-no-alt"></span>';
			html += '</button>';
			html += '</div>';

			html += '<div class="sndp-rb-rules">';
			if ( group && group.rules && group.rules.length ) {
				for ( var r = 0; r < group.rules.length; r++ ) {
					html += this.buildRuleHTML( group.rules[ r ], index + '-' + r );
				}
			} else {
				html += this.buildRuleHTML( null, index + '-0' );
			}
			html += '</div>';

			html += '<div class="sndp-rb-group-footer">';
			html += '<button type="button" class="button button-small sndp-rb-add-rule">';
			html += '<span class="dashicons dashicons-plus-alt2"></span> ' + escHtml( s.cl_add_rule );
			html += '</button>';
			html += '</div>';

			html += '</div>';
			return html;
		},

		buildRuleHTML: function( rule, ruleIndex ) {
			var s = sndp_admin.strings;
			var selectedType = rule ? rule.type : '';

			var html = '<div class="sndp-rb-rule" data-rule-index="' + ruleIndex + '">';

			html += '<select class="sndp-rb-type-select">';
			html += '<option value="">' + escHtml( s.cl_select_type ) + '</option>';

			var lastCategory = '';
			for ( var i = 0; i < this.conditionTypes.length; i++ ) {
				var ct = this.conditionTypes[ i ];
				if ( ct.category !== lastCategory ) {
					if ( lastCategory ) {
						html += '</optgroup>';
					}
					var catLabel = this.categories[ ct.category ] || ct.category;
					html += '<optgroup label="' + escAttr( catLabel ) + '">';
					lastCategory = ct.category;
				}
				html += '<option value="' + escAttr( ct.key ) + '"' + ( selectedType === ct.key ? ' selected' : '' ) + '>';
				html += escHtml( ct.label );
				html += '</option>';
			}
			if ( lastCategory ) {
				html += '</optgroup>';
			}
			html += '</select>';

			html += '<span class="sndp-rb-operator-wrap"></span>';
			html += '<span class="sndp-rb-value-wrap"></span>';

			html += '<button type="button" class="sndp-rb-remove-rule" title="' + escAttr( s.cl_remove_rule ) + '">';
			html += '<span class="dashicons dashicons-trash"></span>';
			html += '</button>';

			html += '</div>';
			return html;
		},

		onTypeChange: function( $rule, typeKey, presetOperator, presetValue ) {
			var ct = this.getConditionType( typeKey );
			var $opWrap = $rule.find( '.sndp-rb-operator-wrap' );
			var $valWrap = $rule.find( '.sndp-rb-value-wrap' );

			var $existingSelect = $valWrap.find( '.sndp-rb-value-select' );
			if ( $existingSelect.length && ( $.fn.selectWoo || $.fn.select2 ) ) {
				try {
					if ( $.fn.selectWoo ) { $existingSelect.selectWoo( 'destroy' ); } else { $existingSelect.select2( 'destroy' ); }
				} catch ( e ) {}
			}

			$opWrap.empty();
			$valWrap.empty();

			if ( ! ct ) {
				return;
			}

			// Operators.
			if ( ct.operators && Object.keys( ct.operators ).length > 0 ) {
				var opHtml = '<select class="sndp-rb-operator-select">';
				for ( var opKey in ct.operators ) {
					if ( ct.operators.hasOwnProperty( opKey ) ) {
						var sel = ( presetOperator && presetOperator === opKey ) ? ' selected' : '';
						opHtml += '<option value="' + escAttr( opKey ) + '"' + sel + '>' + escHtml( ct.operators[ opKey ] ) + '</option>';
					}
				}
				opHtml += '</select>';
				$opWrap.html( opHtml );
			}

			// Values.
			this.renderValueField( $valWrap, ct, presetValue );
		},

		renderValueField: function( $wrap, ct, presetValue ) {
			var self = this;
			var s = sndp_admin.strings;

			if ( ct.values ) {
				if ( typeof ct.values === 'object' && ! Array.isArray( ct.values ) ) {
					var valHtml = '<select class="sndp-rb-value-select" multiple>';
					for ( var vk in ct.values ) {
						if ( ct.values.hasOwnProperty( vk ) ) {
							var isSel = false;
							if ( presetValue ) {
								if ( Array.isArray( presetValue ) ) {
									isSel = presetValue.indexOf( vk ) !== -1;
								} else {
									isSel = ( presetValue === vk );
								}
							}
							valHtml += '<option value="' + escAttr( vk ) + '"' + ( isSel ? ' selected' : '' ) + '>';
							valHtml += escHtml( ct.values[ vk ] );
							valHtml += '</option>';
						}
					}
					valHtml += '</select>';
					$wrap.html( valHtml );
					self.initSelectWoo( $wrap );
				}
			} else if ( ct.valueType ) {
				switch ( ct.valueType ) {
					case 'text':
						var tv = ( presetValue && typeof presetValue === 'string' ) ? presetValue : '';
						$wrap.html( '<input type="text" class="sndp-rb-value-input" value="' + escAttr( tv ) + '" placeholder="' + escAttr( s.cl_enter_value ) + '">' );
						break;

					case 'number':
						var nv = ( presetValue && presetValue !== '' ) ? presetValue : '';
						$wrap.html( '<input type="number" class="sndp-rb-value-input" value="' + escAttr( nv ) + '" placeholder="' + escAttr( s.cl_enter_value ) + '" step="0.01" min="0">' );
						break;

					case 'date_range':
						var startVal = '';
						var endVal = '';
						if ( presetValue && typeof presetValue === 'object' ) {
							startVal = presetValue.start || '';
							endVal = presetValue.end || '';
						}
						var drHtml = '<div class="sndp-rb-date-range">';
						drHtml += '<input type="text" class="sndp-rb-value-input sndp-rb-date-start sndp-datepicker" value="' + escAttr( startVal ) + '" placeholder="' + escAttr( 'Start: YYYY-MM-DD' ) + '">';
						drHtml += '<span class="sndp-rb-date-sep">&ndash;</span>';
						drHtml += '<input type="text" class="sndp-rb-value-input sndp-rb-date-end sndp-datepicker" value="' + escAttr( endVal ) + '" placeholder="' + escAttr( 'End: YYYY-MM-DD' ) + '">';
						drHtml += '</div>';
						$wrap.html( drHtml );

						$wrap.find( '.sndp-datepicker' ).datepicker( {
							dateFormat: 'yy-mm-dd',
							changeMonth: true,
							changeYear: true
						} );
						break;

					case 'dynamic_roles':
					case 'dynamic_post_types':
					case 'dynamic_taxonomy':
					case 'dynamic_wc_categories':
					case 'dynamic_wc_tags':
						this.loadDynamicValues( $wrap, ct.valueType, presetValue );
						break;

					case 'dynamic_pages':
					case 'dynamic_wc_products':
						this.initAjaxSearch( $wrap, ct.valueType, presetValue );
						break;
				}
			}
		},

		initSelectWoo: function( $wrap ) {
			var self = this;
			var $select = $wrap.find( '.sndp-rb-value-select' );
			if ( ! $select.length ) {
				return;
			}

			var fn = $.fn.selectWoo || $.fn.select2;
			if ( ! fn ) {
				return;
			}

			var config = {
				width: '100%',
				placeholder: sndp_admin.strings.cl_select_type || 'Select...',
				allowClear: true
			};

			if ( $.fn.selectWoo ) {
				$select.selectWoo( config );
			} else {
				$select.select2( config );
			}

			$select.on( 'change.sndp', function() {
				self.serializeRules();
			} );
		},

		initAjaxSearch: function( $wrap, valueType, presetValue ) {
			var self = this;
			var action = ( 'dynamic_wc_products' === valueType ) ? 'sndp_search_wc_products' : 'sndp_search_posts';
			var placeholder = ( 'dynamic_wc_products' === valueType ) ? 'Search products...' : 'Search posts/pages...';

			var html = '<select class="sndp-rb-value-select sndp-rb-ajax-select" multiple>';
			if ( presetValue && Array.isArray( presetValue ) ) {
				for ( var i = 0; i < presetValue.length; i++ ) {
					html += '<option value="' + escAttr( presetValue[ i ] ) + '" selected>#' + escHtml( presetValue[ i ] ) + '</option>';
				}
			}
			html += '</select>';
			$wrap.html( html );

			var $select = $wrap.find( '.sndp-rb-ajax-select' );
			var initFn = $.fn.selectWoo || $.fn.select2;
			if ( ! initFn ) {
				return;
			}

			var config = {
				width: '100%',
				placeholder: placeholder,
				allowClear: true,
				minimumInputLength: 2,
				ajax: {
					url: sndp_admin.ajax_url,
					dataType: 'json',
					delay: 300,
					data: function( params ) {
						return {
							action: action,
							nonce: sndp_admin.nonce,
							search: params.term
						};
					},
					processResults: function( data ) {
						var results = [];
						if ( data.success && data.data && data.data.results ) {
							for ( var i = 0; i < data.data.results.length; i++ ) {
								var item = data.data.results[ i ];
								results.push( {
									id: String( item.id ),
									text: item.title + ' (#' + item.id + ')'
								} );
							}
						}
						return { results: results };
					},
					cache: true
				}
			};

			if ( $.fn.selectWoo ) {
				$select.selectWoo( config );
			} else {
				$select.select2( config );
			}

			$select.on( 'change.sndp', function() {
				self.serializeRules();
			} );
		},

		loadDynamicValues: function( $wrap, valueType, presetValue ) {
			var self = this;
			$wrap.html( '<span class="spinner is-active" style="float:none;margin:0;"></span>' );

			$.ajax( {
				url: sndp_admin.ajax_url,
				type: 'POST',
				data: {
					action: 'sndp_get_condition_values',
					nonce: sndp_admin.nonce,
					value_type: valueType
				},
				success: function( response ) {
					if ( ! response.success ) {
						$wrap.html( '<em>Error loading options</em>' );
						return;
					}

					var values = response.data.values;
					var html = '<select class="sndp-rb-value-select" multiple>';

					if ( Array.isArray( values ) ) {
						for ( var g = 0; g < values.length; g++ ) {
							var grp = values[ g ];
							html += '<optgroup label="' + escAttr( grp.label ) + '">';
							for ( var ik in grp.items ) {
								if ( grp.items.hasOwnProperty( ik ) ) {
									var isSel = presetValue && Array.isArray( presetValue ) && presetValue.indexOf( ik ) !== -1;
									html += '<option value="' + escAttr( ik ) + '"' + ( isSel ? ' selected' : '' ) + '>' + escHtml( grp.items[ ik ] ) + '</option>';
								}
							}
							html += '</optgroup>';
						}
					} else {
						for ( var vk in values ) {
							if ( values.hasOwnProperty( vk ) ) {
								var sel = false;
								if ( presetValue ) {
									if ( Array.isArray( presetValue ) ) {
										sel = presetValue.indexOf( vk ) !== -1;
									} else {
										sel = ( presetValue === vk );
									}
								}
								html += '<option value="' + escAttr( vk ) + '"' + ( sel ? ' selected' : '' ) + '>' + escHtml( values[ vk ] ) + '</option>';
							}
						}
					}

					html += '</select>';
					$wrap.html( html );
					self.initSelectWoo( $wrap );
				},
				error: function() {
					$wrap.html( '<em>Error loading options</em>' );
				}
			} );
		},

		getConditionType: function( key ) {
			for ( var i = 0; i < this.conditionTypes.length; i++ ) {
				if ( this.conditionTypes[ i ].key === key ) {
					return this.conditionTypes[ i ];
				}
			}
			return null;
		},

		addGroup: function() {
			var html = this.buildGroupHTML( null, this.groupCounter );
			var $new = $( html ).hide();
			this.$body.find( '.sndp-rb-groups' ).append( $new );
			$new.slideDown( 200 );
			this.updateGroupLabels();
			this.updateGlobalMatchVisibility();
			this.serializeRules();
		},

		addRule: function( $group ) {
			this.ruleCounter++;
			var ruleIndex = $group.data( 'group-index' ) + '-' + this.ruleCounter;
			var html = this.buildRuleHTML( null, ruleIndex );
			var $new = $( html ).hide();
			$group.find( '.sndp-rb-rules' ).append( $new );
			$new.slideDown( 150 );
		},

		updateGroupLabels: function() {
			var s = sndp_admin.strings;
			this.$body.find( '.sndp-rb-group' ).each( function( idx ) {
				$( this ).find( '.sndp-rb-group-label' ).first().text( s.cl_group_label + ' ' + ( idx + 1 ) );
				$( this ).attr( 'data-group-index', idx );
			} );
		},

		updateGlobalMatchVisibility: function() {
			var groupCount = this.$body.find( '.sndp-rb-group' ).length;
			var $bar = this.$body.find( '.sndp-rb-global-bar' );

			if ( groupCount > 1 && ! $bar.length ) {
				var s = sndp_admin.strings;
				var html = '<div class="sndp-rb-global-bar">';
				html += '<label>' + escHtml( s.cl_groups_match ) + ' </label>';
				html += '<select class="sndp-rb-global-match">';
				html += '<option value="all">' + escHtml( s.cl_match_all ) + '</option>';
				html += '<option value="any">' + escHtml( s.cl_match_any ) + '</option>';
				html += '</select>';
				html += '</div>';
				this.$body.find( '.sndp-rb-builder' ).prepend( html );
			} else if ( groupCount <= 1 && $bar.length ) {
				$bar.remove();
			}
		},

		serializeRules: function() {
			var rules = {
				enabled: $( '#sndp-enable-conditions' ).is( ':checked' ),
				match: this.$body.find( '.sndp-rb-global-match' ).val() || 'all',
				groups: []
			};

			this.$body.find( '.sndp-rb-group' ).each( function() {
				var group = {
					match: $( this ).find( '.sndp-rb-group-match' ).val() || 'all',
					rules: []
				};

				$( this ).find( '.sndp-rb-rule' ).each( function() {
					var type = $( this ).find( '.sndp-rb-type-select' ).val();
					if ( ! type ) {
						return;
					}

					var operator = $( this ).find( '.sndp-rb-operator-select' ).val() || 'is';
					var value = '';

					var $valSelect = $( this ).find( '.sndp-rb-value-select' );
					var $valInput = $( this ).find( '.sndp-rb-value-input' );

					if ( $valSelect.length ) {
						value = $valSelect.val();
						if ( ! value || ( Array.isArray( value ) && ! value.length ) ) {
							value = '';
						}
					} else if ( $( this ).find( '.sndp-rb-date-range' ).length ) {
						value = {
							start: $( this ).find( '.sndp-rb-date-start' ).val() || '',
							end: $( this ).find( '.sndp-rb-date-end' ).val() || ''
						};
					} else if ( $valInput.length ) {
						value = $valInput.val();
					}

					if ( type === 'page' && typeof value === 'string' ) {
						value = value.split( ',' ).map( function( v ) { return v.trim(); } ).filter( Boolean );
					}

					group.rules.push( {
						type: type,
						operator: operator,
						value: value
					} );
				} );

				if ( group.rules.length ) {
					rules.groups.push( group );
				}
			} );

			$( '#sndp-conditional-rules' ).val( JSON.stringify( rules ) );
		}
	};

	/**
	 * Testing Mode — stage changes without affecting live visitors.
	 */
	var SNDP_TestingMode = {
		init: function() {
			$( document ).on( 'change', '#sndp-testing-mode-toggle, .sndp-testing-mode-toggle', this.handleToggle.bind( this ) );
			$( document ).on( 'click', '#sndp-testing-publish, #sndp-tm-publish', this.handlePublish.bind( this ) );
			$( document ).on( 'click', '#sndp-testing-discard, #sndp-tm-discard', this.handleDiscard.bind( this ) );
			$( document ).on( 'click', '#sndp-testing-view-changes, #sndp-tm-view-changes', this.handleViewChanges.bind( this ) );
		},

		handleToggle: function( e ) {
			var $cb    = $( e.target );
			var enable = $cb.prop( 'checked' );

			$cb.prop( 'checked', ! enable );

			if ( enable ) {
				SNDP_Admin.confirmModal( {
					icon: 'visibility',
					type: 'info',
					title: sndp_admin.strings.tm_enable_title,
					message: '<p>' + sndp_admin.strings.tm_enable_desc + '</p>',
					buttons: [
						{ label: sndp_admin.strings.cancel, class: '', id: 'sndp-cm-cancel' },
						{ label: sndp_admin.strings.tm_enable_btn, class: 'button-primary', id: 'sndp-cm-confirm' }
					],
					handlers: {
						'sndp-cm-cancel': function() { SNDP_Admin.closeConfirm(); },
						'sndp-cm-confirm': function( $btn ) {
							$btn.prop( 'disabled', true ).text( sndp_admin.strings.enabling );
							SNDP_TestingMode.doEnable();
						}
					}
				} );
			} else {
				SNDP_TestingMode.showDisableDialog();
			}
		},

		showDisableDialog: function() {
			$.post( sndp_admin.ajax_url, {
				action: 'sndp_get_testing_changes',
				nonce: sndp_admin.nonce
			}, function( response ) {
				if ( ! response.success ) {
					return;
				}

				var changes    = response.data.changes;
				var hasChanges = ( changes.snippets && changes.snippets.length ) || ( changes.global && changes.global.length );

				if ( hasChanges ) {
					var body = '<p>' + sndp_admin.strings.tm_deactivate_prompt + '</p>' + SNDP_TestingMode.buildChangesHtml( changes );

					SNDP_Admin.confirmModal( {
						icon: 'warning',
						type: 'warning',
						title: sndp_admin.strings.tm_deactivate_title,
						message: body,
						buttons: [
							{ label: sndp_admin.strings.cancel, class: '', id: 'sndp-cm-cancel' },
							{ label: sndp_admin.strings.tm_discard_btn, class: 'button-link-delete', id: 'sndp-cm-discard' },
							{ label: sndp_admin.strings.tm_publish_btn, class: 'button-primary', id: 'sndp-cm-publish' }
						],
						handlers: {
							'sndp-cm-cancel': function() { SNDP_Admin.closeConfirm(); },
							'sndp-cm-publish': function( $btn ) {
								$btn.prop( 'disabled', true ).text( sndp_admin.strings.publishing );
								SNDP_TestingMode.doPublish();
							},
							'sndp-cm-discard': function( $btn ) {
								$btn.prop( 'disabled', true ).text( sndp_admin.strings.discarding );
								SNDP_TestingMode.doDiscard();
							}
						}
					} );
				} else {
					SNDP_Admin.confirmModal( {
						icon: 'visibility',
						type: 'info',
						title: sndp_admin.strings.tm_deactivate_title,
						message: '<p>' + sndp_admin.strings.tm_no_changes_disable + '</p>',
						buttons: [
							{ label: sndp_admin.strings.cancel, class: '', id: 'sndp-cm-cancel' },
							{ label: sndp_admin.strings.tm_disable_btn, class: 'button-primary', id: 'sndp-cm-confirm' }
						],
						handlers: {
							'sndp-cm-cancel': function() { SNDP_Admin.closeConfirm(); },
							'sndp-cm-confirm': function( $btn ) {
								$btn.prop( 'disabled', true ).text( sndp_admin.strings.discarding );
								SNDP_TestingMode.doDiscard();
							}
						}
					} );
				}
			} );
		},

		doEnable: function() {
			$.post( sndp_admin.ajax_url, {
				action: 'sndp_toggle_testing_mode',
				nonce: sndp_admin.nonce,
				enable: 'true'
			}, function( response ) {
				SNDP_Admin.closeConfirm();
				if ( response.success ) {
					SNDP_Admin.showNotice( response.data.message, 'success' );
					setTimeout( function() { location.reload(); }, 800 );
				} else {
					SNDP_Admin.showNotice( response.data.message || sndp_admin.strings.error );
				}
			} );
		},

		doPublish: function() {
			$.post( sndp_admin.ajax_url, {
				action: 'sndp_publish_testing_changes',
				nonce: sndp_admin.nonce
			}, function( response ) {
				SNDP_Admin.closeConfirm();
				if ( response.success ) {
					SNDP_Admin.showNotice( response.data.message, 'success' );
					setTimeout( function() { location.reload(); }, 800 );
				} else {
					SNDP_Admin.showNotice( response.data.message || sndp_admin.strings.error );
				}
			} );
		},

		doDiscard: function() {
			$.post( sndp_admin.ajax_url, {
				action: 'sndp_discard_testing_changes',
				nonce: sndp_admin.nonce
			}, function( response ) {
				SNDP_Admin.closeConfirm();
				if ( response.success ) {
					SNDP_Admin.showNotice( response.data.message, 'success' );
					setTimeout( function() { location.reload(); }, 800 );
				} else {
					SNDP_Admin.showNotice( response.data.message || sndp_admin.strings.error );
				}
			} );
		},

		handlePublish: function( e ) {
			e.preventDefault();
			SNDP_Admin.confirmModal( {
				icon: 'upload',
				type: 'info',
				title: sndp_admin.strings.tm_publish_title,
				message: '<p>' + sndp_admin.strings.tm_publish_confirm + '</p>',
				buttons: [
					{ label: sndp_admin.strings.cancel, class: '', id: 'sndp-cm-cancel' },
					{ label: sndp_admin.strings.tm_publish_btn, class: 'button-primary', id: 'sndp-cm-confirm' }
				],
				handlers: {
					'sndp-cm-cancel': function() { SNDP_Admin.closeConfirm(); },
					'sndp-cm-confirm': function( $btn ) {
						$btn.prop( 'disabled', true ).text( sndp_admin.strings.publishing );
						SNDP_TestingMode.doPublish();
					}
				}
			} );
		},

		handleDiscard: function( e ) {
			e.preventDefault();
			SNDP_Admin.confirmModal( {
				icon: 'trash',
				type: 'danger',
				title: sndp_admin.strings.tm_discard_title,
				message: '<p>' + sndp_admin.strings.tm_discard_confirm + '</p>',
				buttons: [
					{ label: sndp_admin.strings.cancel, class: '', id: 'sndp-cm-cancel' },
					{ label: sndp_admin.strings.tm_discard_btn, class: 'button-link-delete', id: 'sndp-cm-confirm' }
				],
				handlers: {
					'sndp-cm-cancel': function() { SNDP_Admin.closeConfirm(); },
					'sndp-cm-confirm': function( $btn ) {
						$btn.prop( 'disabled', true ).text( sndp_admin.strings.discarding );
						SNDP_TestingMode.doDiscard();
					}
				}
			} );
		},

		handleViewChanges: function( e ) {
			e.preventDefault();
			$.post( sndp_admin.ajax_url, {
				action: 'sndp_get_testing_changes',
				nonce: sndp_admin.nonce
			}, function( response ) {
				if ( response.success ) {
					SNDP_TestingMode.showChangesDialog( response.data.changes );
				}
			} );
		},

		buildChangesHtml: function( changes ) {
			var html = '<div class="sndp-tm-changes-list">';
			var hasSnippets = changes.snippets && changes.snippets.length;
			var hasGlobal   = changes.global && changes.global.length;

			if ( ! hasSnippets && ! hasGlobal ) {
				html += '<p>' + ( sndp_admin.strings.tm_no_changes || 'No changes detected.' ) + '</p>';
			}

			if ( hasSnippets ) {
				html += '<div class="sndp-tm-changes-section">';
				html += '<h4>Snippets (' + changes.snippets.length + ')</h4>';
				for ( var i = 0; i < changes.snippets.length; i++ ) {
					var s = changes.snippets[ i ];
					var badge = 'modified';
					var badgeLabel = sndp_admin.strings.tm_snippet_modified || 'Modified';
					if ( 'new' === s.type ) {
						badge = 'new';
						badgeLabel = sndp_admin.strings.tm_snippet_new || 'New';
					} else if ( 'deleted' === s.type ) {
						badge = 'deleted';
						badgeLabel = sndp_admin.strings.tm_snippet_deleted || 'Deleted';
					}
					html += '<div class="sndp-tm-change-item">';
					html += '<span>' + $( '<span>' ).text( s.title ).html() + '</span>';
					html += '<span class="sndp-tm-badge sndp-tm-badge-' + badge + '">' + badgeLabel + '</span>';
					html += '</div>';
				}
				html += '</div>';
			}

			if ( hasGlobal ) {
				html += '<div class="sndp-tm-changes-section">';
				html += '<h4>Global Scripts (' + changes.global.length + ')</h4>';
				for ( var j = 0; j < changes.global.length; j++ ) {
					var g = changes.global[ j ];
					html += '<div class="sndp-tm-change-item">';
					html += '<span>' + $( '<span>' ).text( g.label ).html() + '</span>';
					html += '<span class="sndp-tm-badge sndp-tm-badge-changed">' + ( sndp_admin.strings.tm_global_changed || 'Changed' ) + '</span>';
					html += '</div>';
				}
				html += '</div>';
			}

			html += '</div>';
			return html;
		},

		showChangesDialog: function( changes ) {
			SNDP_Admin.openModal( sndp_admin.strings.tm_changes_title || 'Staged Changes', this.buildChangesHtml( changes ) );
		}
	};

	/**
	 * Plugin Importer — handles importing snippets from other plugins.
	 */
	var SNDP_Importer = {
		currentSource: '',
		currentSourceName: '',
		queue: [],
		imported: 0,
		failed: 0,

		init: function() {
			// Tab switching.
			$( document ).on( 'click', '.sndp-import-tab', this.switchTab );

			// Source selection.
			$( document ).on( 'click', '.sndp-importer-source-item:not(.disabled)', this.selectSource.bind( this ) );

			// Back button.
			$( '#sndp-importer-back' ).on( 'click', this.backToSources.bind( this ) );

			// Select all toggle.
			$( '#sndp-importer-select-all' ).on( 'change', this.toggleSelectAll );

			// Individual checkbox.
			$( document ).on( 'change', '.sndp-importer-snippet-check', this.updateImportButton );

			// Import button.
			$( '#sndp-importer-import-btn' ).on( 'click', this.startImport.bind( this ) );
		},

		switchTab: function() {
			var tab = $( this ).data( 'tab' );
			$( '.sndp-import-tab' ).removeClass( 'active' );
			$( this ).addClass( 'active' );
			$( '.sndp-import-tab-content' ).addClass( 'hidden' );
			$( '.sndp-import-tab-content[data-tab="' + tab + '"]' ).removeClass( 'hidden' );

			if ( 'plugin' === tab && $( '#sndp-importer-source-list .sndp-importer-source-item' ).length === 0 ) {
				SNDP_Importer.loadSources();
			}
		},

		loadSources: function() {
			var $list = $( '#sndp-importer-source-list' );
			$list.html( '<p class="sndp-loading"><span class="spinner is-active"></span> ' + ( sndp_admin.strings.imp_loading || 'Detecting plugins...' ) + '</p>' );

			$.post( sndp_admin.ajax_url, {
				action: 'sndp_get_importers',
				nonce: sndp_admin.nonce
			}, function( response ) {
				if ( ! response.success || ! response.data.importers || $.isEmptyObject( response.data.importers ) ) {
					$list.html( '<p>' + ( sndp_admin.strings.imp_no_plugins || 'No compatible snippet plugins detected.' ) + '</p>' );
					return;
				}

				var html = '';
				$.each( response.data.importers, function( slug, data ) {
					var status = '';
					var disabled = '';

					if ( data.active ) {
						status = '<span class="sndp-importer-source-status" style="color: #00a32a;">&#9679; Active</span>';
					} else if ( data.installed ) {
						status = '<span class="sndp-importer-source-status">' + ( sndp_admin.strings.imp_not_active || '(Not Active)' ) + '</span>';
					} else if ( data.has_data ) {
						status = '<span class="sndp-importer-source-status">' + ( sndp_admin.strings.imp_has_data || '(Deactivated — data found)' ) + '</span>';
					}

					html += '<div class="sndp-importer-source-item" data-slug="' + slug + '">';
					html += '<span><span class="sndp-importer-source-name">' + data.name + '</span>' + status + '</span>';
					html += '<span class="sndp-importer-source-arrow dashicons dashicons-arrow-right-alt2"></span>';
					html += '</div>';
				} );

				$list.html( html );
			} );
		},

		selectSource: function( e ) {
			var $item = $( e.currentTarget );
			var slug = $item.data( 'slug' );
			var name = $item.find( '.sndp-importer-source-name' ).text();

			this.currentSource = slug;
			this.currentSourceName = name;

			$( '#sndp-importer-sources' ).addClass( 'hidden' );
			$( '#sndp-importer-snippets' ).removeClass( 'hidden' );
			$( '#sndp-importer-source-name' ).text( name );

			this.loadSnippets( slug );
		},

		loadSnippets: function( slug ) {
			var $list = $( '#sndp-importer-snippet-list' );
			$list.html( '<div style="padding: 12px;"><span class="spinner is-active" style="float:none;"></span> ' + ( sndp_admin.strings.imp_loading_snippets || 'Loading snippets...' ) + '</div>' );
			$( '#sndp-importer-import-btn' ).prop( 'disabled', true );
			$( '#sndp-importer-select-all' ).prop( 'checked', false );
			$( '#sndp-importer-count' ).text( '' );

			$.post( sndp_admin.ajax_url, {
				action: 'sndp_get_source_snippets',
				nonce: sndp_admin.nonce,
				source: slug
			}, function( response ) {
				if ( ! response.success || ! response.data.snippets || $.isEmptyObject( response.data.snippets ) ) {
					$list.html( '<div style="padding: 12px;">' + ( sndp_admin.strings.imp_no_snippets || 'No snippets found.' ) + '</div>' );
					return;
				}

				var html = '';
				var count = 0;
				$.each( response.data.snippets, function( id, title ) {
					count++;
					html += '<div class="sndp-importer-snippet-item">';
					html += '<label>';
					html += '<input type="checkbox" class="sndp-importer-snippet-check" value="' + id + '">';
					html += '<span>' + $( '<span>' ).text( title ).html() + '</span>';
					html += '</label>';
					html += '</div>';
				} );

				$list.html( html );
				$( '#sndp-importer-count' ).text( count + ' snippet' + ( count !== 1 ? 's' : '' ) );
			} );
		},

		backToSources: function() {
			$( '#sndp-importer-snippets' ).addClass( 'hidden' );
			$( '#sndp-importer-progress' ).addClass( 'hidden' );
			$( '#sndp-importer-sources' ).removeClass( 'hidden' );
			this.currentSource = '';
			this.currentSourceName = '';
		},

		toggleSelectAll: function() {
			var checked = $( this ).prop( 'checked' );
			$( '.sndp-importer-snippet-check' ).prop( 'checked', checked );
			SNDP_Importer.updateImportButton();
		},

		updateImportButton: function() {
			var checked = $( '.sndp-importer-snippet-check:checked' ).length;
			$( '#sndp-importer-import-btn' ).prop( 'disabled', checked === 0 );

			var total = $( '.sndp-importer-snippet-check' ).length;
			$( '#sndp-importer-select-all' ).prop( 'checked', checked === total && total > 0 );
		},

		startImport: function() {
			var self = this;
			self.queue = [];
			self.imported = 0;
			self.failed = 0;

			$( '.sndp-importer-snippet-check:checked' ).each( function() {
				self.queue.push( $( this ).val() );
			} );

			if ( ! self.queue.length ) {
				return;
			}

			$( '#sndp-importer-snippets' ).addClass( 'hidden' );
			$( '#sndp-importer-progress' ).removeClass( 'hidden' );
			$( '#sndp-importer-results' ).html( '' );
			$( '.sndp-importer-progress-fill' ).css( 'width', '0%' );

			self.total = self.queue.length;
			self.processNext();
		},

		processNext: function() {
			var self = this;

			if ( ! self.queue.length ) {
				self.onComplete();
				return;
			}

			var sourceId = self.queue.shift();
			var current = self.imported + self.failed + 1;
			var pct = Math.round( ( current / self.total ) * 100 );

			$( '.sndp-importer-progress-fill' ).css( 'width', pct + '%' );

			var progressText = ( sndp_admin.strings.imp_progress || 'Importing %1$s of %2$s snippets from %3$s...' )
				.replace( '%1$s', current )
				.replace( '%2$s', self.total )
				.replace( '%3$s', self.currentSourceName );
			$( '#sndp-importer-progress-text' ).text( progressText );

			$.post( sndp_admin.ajax_url, {
				action: 'sndp_import_from_plugin',
				nonce: sndp_admin.nonce,
				source: self.currentSource,
				source_id: sourceId
			}, function( response ) {
				if ( response.success ) {
					self.imported++;
					var item = '<div class="sndp-importer-result-item">';
					item += '<span><span class="dashicons dashicons-yes-alt"></span> ' + $( '<span>' ).text( response.data.title ).html() + '</span>';
					item += '<a href="' + response.data.edit + '" target="_blank">' + ( sndp_admin.strings.imp_edit || 'Edit' ) + '</a>';
					item += '</div>';
					$( '#sndp-importer-results' ).append( item );
				} else {
					self.failed++;
					var msg = response.data && response.data.message ? response.data.message : ( sndp_admin.strings.imp_error || 'Failed' );
					var failItem = '<div class="sndp-importer-result-item">';
					failItem += '<span><span class="dashicons dashicons-dismiss"></span> ' + msg + ' (ID: ' + sourceId + ')</span>';
					failItem += '</div>';
					$( '#sndp-importer-results' ).append( failItem );
				}

				self.processNext();
			} ).fail( function() {
				self.failed++;
				self.processNext();
			} );
		},

		onComplete: function() {
			$( '.sndp-importer-progress-fill' ).css( 'width', '100%' );
			$( '#sndp-importer-progress-text' ).text( '' );

			var msg = ( sndp_admin.strings.imp_complete || 'Successfully imported %d snippet(s)!' ).replace( '%d', this.imported );
			var html = '<div class="sndp-importer-complete">';
			html += '<span class="dashicons dashicons-yes-alt"></span> ' + msg;
			html += '</div>';
			html += '<p style="margin-top: 12px;">';
			html += '<button type="button" class="button" id="sndp-importer-done">' + ( sndp_admin.strings.imp_back || 'Back to Sources' ) + '</button>';
			html += ' <a href="' + window.location.href + '" class="button button-primary">' + sndp_admin.strings.loading_btn.replace( '...', '' ) + 'Reload Page</a>';
			html += '</p>';
			$( '#sndp-importer-progress' ).append( html );

			$( '#sndp-importer-done' ).on( 'click', function() {
				SNDP_Importer.backToSources();
			} );
		}
	};

	/**
	 * Activity Log module.
	 */
	var SNDP_ActivityLog = {

		currentPage: 1,
		currentFilter: '',
		loading: false,

		init: function() {
			var self = this;

			$( '#sndp-al-filter' ).on( 'change', function() {
				self.currentFilter = $( this ).val();
				self.currentPage = 1;
				self.load();
			} );

			$( '#sndp-al-clear' ).on( 'click', function() {
				self.clearLog();
			} );

			$( document ).on( 'click', '#sndp-al-load-more', function() {
				if ( ! self.loading ) {
					self.loadMore();
				}
			} );
		},

		load: function() {
			var self = this;
			var $wrap = $( '#sndp-al-table-wrap' );

			self.loading = true;
			$wrap.html( '<div class="sndp-al-loading"><span class="spinner is-active"></span> ' + ( sndp_admin.strings.loading || 'Loading...' ) + '</div>' );

			$.post( sndp_admin.ajax_url, {
				action: 'sndp_get_activity_log',
				nonce: sndp_admin.nonce,
				log_type: self.currentFilter,
				log_page: 1
			}, function( response ) {
				self.loading = false;

				if ( ! response.success || ! response.data.html ) {
					$wrap.html(
						'<div class="sndp-al-empty">' +
						'<span class="dashicons dashicons-clipboard"></span>' +
						'<h3>' + ( sndp_admin.strings.al_no_activity || 'No matching events' ) + '</h3>' +
						'<p>' + ( sndp_admin.strings.al_empty || 'Events will appear here as you manage your snippets.' ) + '</p>' +
						'</div>'
					);
					return;
				}

				var html = '<table class="wp-list-table widefat fixed striped sndp-al-table" id="sndp-al-table">' +
					'<thead><tr>' +
					'<th class="sndp-al-col-event" scope="col">' + ( sndp_admin.strings.al_event || 'Event' ) + '</th>' +
					'<th class="sndp-al-col-details" scope="col">' + ( sndp_admin.strings.al_details || 'Details' ) + '</th>' +
					'<th class="sndp-al-col-context" scope="col">' + ( sndp_admin.strings.al_source || 'Source' ) + '</th>' +
					'<th class="sndp-al-col-user" scope="col">' + ( sndp_admin.strings.al_user || 'User' ) + '</th>' +
					'<th class="sndp-al-col-time" scope="col">' + ( sndp_admin.strings.al_time || 'Time' ) + '</th>' +
					'</tr></thead>' +
					'<tbody id="sndp-al-body">' + response.data.html + '</tbody></table>';

				var footerHtml = '<div class="sndp-al-footer">' +
					'<span class="sndp-al-pagination-info">' +
					( sndp_admin.strings.al_showing || 'Showing' ) + ' ' + Math.min( 20, response.data.total ) + ' ' + ( sndp_admin.strings.of || 'of' ) + ' ' + response.data.total + ' ' + ( sndp_admin.strings.al_events || 'events' ) +
					'</span>';

				if ( response.data.total_pages > 1 ) {
					footerHtml += '<button type="button" class="button button-secondary" id="sndp-al-load-more" data-page="1" data-total-pages="' + response.data.total_pages + '">' +
						( sndp_admin.strings.al_load_more || 'Load More' ) +
						'</button>';
				}

				footerHtml += '</div>';
				html += footerHtml;

				$wrap.html( html );
				self.currentPage = 1;
			} ).fail( function() {
				self.loading = false;
				$wrap.html( '<div class="sndp-al-empty"><p>' + ( sndp_admin.strings.error || 'An error occurred.' ) + '</p></div>' );
			} );
		},

		loadMore: function() {
			var self = this;
			var $btn = $( '#sndp-al-load-more' );
			var nextPage = parseInt( $btn.data( 'page' ), 10 ) + 1;
			var totalPages = parseInt( $btn.data( 'total-pages' ), 10 );

			if ( nextPage > totalPages ) {
				return;
			}

			self.loading = true;
			$btn.prop( 'disabled', true ).text( sndp_admin.strings.loading || 'Loading...' );

			$.post( sndp_admin.ajax_url, {
				action: 'sndp_get_activity_log',
				nonce: sndp_admin.nonce,
				log_type: self.currentFilter,
				log_page: nextPage
			}, function( response ) {
				self.loading = false;

				if ( response.success && response.data.html ) {
					$( '#sndp-al-body' ).append( response.data.html );
					$btn.data( 'page', nextPage );

					if ( nextPage >= totalPages ) {
						$( '#sndp-al-pagination' ).remove();
					} else {
						$btn.prop( 'disabled', false ).text( sndp_admin.strings.al_load_more || 'Load More' );
					}
				}
			} ).fail( function() {
				self.loading = false;
				$btn.prop( 'disabled', false ).text( sndp_admin.strings.al_load_more || 'Load More' );
			} );
		},

		clearLog: function() {
			SNDP_Admin.confirmModal( {
				icon: 'trash',
				type: 'danger',
				title: sndp_admin.strings.al_clear_title || 'Clear Activity Log',
				message: '<p>' + ( sndp_admin.strings.al_clear_desc || 'Are you sure you want to clear the entire activity log? This action cannot be undone.' ) + '</p>',
				buttons: [
					{ id: 'sndp-al-confirm-clear', label: sndp_admin.strings.al_clear_btn || 'Clear Log', 'class': 'button-link-delete' },
					{ id: 'sndp-al-cancel-clear', label: sndp_admin.strings.cancel || 'Cancel', 'class': '' }
				],
				handlers: {
					'sndp-al-cancel-clear': function() {
						SNDP_Admin.closeConfirm();
					},
					'sndp-al-confirm-clear': function( $btn ) {
						$btn.prop( 'disabled', true ).text( sndp_admin.strings.deleting || 'Deleting...' );

						$.post( sndp_admin.ajax_url, {
							action: 'sndp_clear_activity_log',
							nonce: sndp_admin.nonce
						}, function( response ) {
							SNDP_Admin.closeConfirm();

							if ( response.success ) {
								$( '#sndp-al-table-wrap' ).html(
									'<div class="sndp-al-empty">' +
									'<span class="dashicons dashicons-clipboard"></span>' +
									'<h3>' + ( sndp_admin.strings.al_no_activity || 'No activity yet' ) + '</h3>' +
									'<p>' + ( sndp_admin.strings.al_empty || 'No activity recorded yet. Events will appear here as you manage your snippets.' ) + '</p>' +
									'</div>'
								);
								$( '.sndp-al-toolbar-count' ).remove();
								$( '#sndp-al-clear' ).remove();
							}
						} ).fail( function() {
							SNDP_Admin.closeConfirm();
						} );
					}
				}
			} );
		}
	};

	// Initialize on document ready.
	$( document ).ready( function() {
		SNDP_Admin.init();
		SNDP_RuleBuilder.init();
		SNDP_TestingMode.init();
		SNDP_Importer.init();
		SNDP_ActivityLog.init();
	} );

} )( jQuery );
