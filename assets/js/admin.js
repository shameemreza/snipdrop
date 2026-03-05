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

			// Revisions.
			$( document ).on( 'click', '.sndp-restore-revision', this.handleRestoreRevision );
			$( document ).on( 'click', '.sndp-view-diff', this.handleViewDiff );

			// ESC key to close modal.
			$( document ).on( 'keyup', function( e ) {
				if ( 27 === e.keyCode ) {
					SNDP_Admin.closeModal();
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

			// Dark mode toggle.
			this.initDarkMode();

			this.initDatepickers();
			this.togglePhpOptions();
			this.toggleConditionalOptions();
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
			$card.addClass( 'sndp-loading' );

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
					$card.removeClass( 'sndp-loading' );
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

			var $btn = $( this );
			var snippetId = $btn.data( 'snippet-id' );

			$btn.prop( 'disabled', true );

			$.ajax( {
				url: sndp_admin.ajax_url,
				type: 'POST',
				data: {
					action: 'sndp_copy_to_custom',
					nonce: sndp_admin.nonce,
					snippet_id: snippetId
				},
				success: function( response ) {
					if ( response.success ) {
						if ( confirm( sndp_admin.strings.copied + ' ' + sndp_admin.strings.edit_now ) ) {
							window.location.href = response.data.edit_url;
						}
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

			if ( ! confirm( sndp_admin.strings.confirm_delete ) ) {
				return;
			}

			var $link = $( this );
			var snippetId = $link.data( 'snippet-id' );
			var $row = $link.closest( 'tr' );

			$.ajax( {
				url: sndp_admin.ajax_url,
				type: 'POST',
				data: {
					action: 'sndp_delete_custom_snippet',
					nonce: sndp_admin.nonce,
					snippet_id: snippetId
				},
				success: function( response ) {
					if ( response.success ) {
						$row.fadeOut( function() {
							$( this ).remove();
						} );
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
					schedule_end: SNDP_Admin.getScheduleValue( 'end' )
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

			if ( 'delete' === action && ! confirm( sndp_admin.strings.confirm_delete ) ) {
				return;
			}

			var $btn = $( this );
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
				$row.toggle( title.indexOf( query ) !== -1 || type.indexOf( query ) !== -1 );
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

		closeModal: function() {
			$( '.sndp-modal' ).removeClass( 'sndp-modal-open' );
		}
	};

	// Initialize on document ready.
	$( document ).ready( function() {
		SNDP_Admin.init();
	} );

} )( jQuery );
