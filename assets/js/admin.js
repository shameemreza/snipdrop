/**
 * SnipDrop Admin JavaScript
 *
 * @package SnipDrop
 * @since   1.0.0
 */

( function( $ ) {
	'use strict';

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

			// ESC key to close modal.
			$( document ).on( 'keyup', function( e ) {
				if ( 27 === e.keyCode ) {
					SNDP_Admin.closeModal();
				}
			} );
		},

		/**
		 * Initialize library (load snippets via AJAX).
		 */
		initLibrary: function() {
			if ( $( '#sndp-snippets-grid' ).length ) {
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

			// Toggle PHP options visibility.
			this.togglePhpOptions();

			// Toggle conditional options visibility.
			this.toggleConditionalOptions();
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
				$shortcodeHint.show();
			} else {
				$shortcodeHint.hide();
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
							$card.addClass( 'enabled' );
							$toggle.prop( 'checked', true );
						} else {
							$card.removeClass( 'enabled' );
							$toggle.prop( 'checked', false );
						}
					} else {
						// Check for missing plugins error.
						var errorMsg = response.data.message || sndp_admin.strings.error;
						if ( response.data.missing_plugins && response.data.missing_plugins.length ) {
							errorMsg = sndp_admin.strings.plugin_required + '\n\n' + response.data.missing_plugins.join( '\n' );
						}
						alert( errorMsg );
						$toggle.prop( 'checked', ! $toggle.prop( 'checked' ) );
					}
				},
				error: function() {
					alert( sndp_admin.strings.error );
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
						alert( response.data.message || sndp_admin.strings.error );
					}
				},
				error: function() {
					alert( sndp_admin.strings.error );
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
				// Convert newlines to paragraphs.
				var descHtml = data.long_description
					.split( /\n\n+/ )
					.map( function( p ) { return '<p>' + p.replace( /\n/g, '<br>' ) + '</p>'; } )
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
				var authorHtml = '<strong>Author:</strong> ';
				if ( data.author.url ) {
					authorHtml += '<a href="' + data.author.url + '" target="_blank" rel="noopener noreferrer">' + data.author.name + '</a>';
				} else {
					authorHtml += data.author.name;
				}
				$author.html( authorHtml );
				hasCredits = true;
			}

			if ( data.source && data.source.name ) {
				var sourceHtml = '<strong>Source:</strong> ';
				if ( data.source.url ) {
					sourceHtml += '<a href="' + data.source.url + '" target="_blank" rel="noopener noreferrer">' + data.source.name + '</a>';
				} else {
					sourceHtml += data.source.name;
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
			errorHtml += '<p class="sndp-error-message">' + message + '</p>';
			errorHtml += '<p class="sndp-error-hint">This may be a temporary issue. Try syncing the library or wait a moment.</p>';
			errorHtml += '<div class="sndp-error-actions">';
			errorHtml += '<button type="button" class="button sndp-retry-fetch" data-snippet-id="' + snippetId + '" data-title="' + title + '">Try Again</button>';
			errorHtml += '<button type="button" class="button sndp-sync-and-retry" data-snippet-id="' + snippetId + '" data-title="' + title + '">Sync Library</button>';
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

			$btn.prop( 'disabled', true ).text( 'Loading...' );
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

			$btn.prop( 'disabled', true ).text( 'Syncing...' );
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
						alert( response.data.message || sndp_admin.strings.error );
					}
				},
				error: function() {
					alert( sndp_admin.strings.error );
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
			var html = '<div class="sndp-configure-field">';
			html += '<label for="sndp-config-' + setting.id + '">' + setting.label + '</label>';

			switch ( setting.type ) {
				case 'textarea':
					html += '<textarea id="sndp-config-' + setting.id + '" name="' + setting.id + '" rows="3">' + value + '</textarea>';
					break;

				case 'select':
					html += '<select id="sndp-config-' + setting.id + '" name="' + setting.id + '">';
					if ( setting.options ) {
						$.each( setting.options, function( optValue, optLabel ) {
							var selected = value === optValue ? ' selected' : '';
							html += '<option value="' + optValue + '"' + selected + '>' + optLabel + '</option>';
						} );
					}
					html += '</select>';
					break;

				case 'number':
					html += '<input type="number" id="sndp-config-' + setting.id + '" name="' + setting.id + '" value="' + value + '" class="small-text">';
					break;

				default: // text
					html += '<input type="text" id="sndp-config-' + setting.id + '" name="' + setting.id + '" value="' + value + '" class="regular-text">';
			}

			if ( setting.description ) {
				html += '<p class="description">' + setting.description + '</p>';
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
						alert( response.data.message || sndp_admin.strings.error );
					}
				},
				error: function() {
					alert( sndp_admin.strings.error );
				},
				complete: function() {
					$form.find( 'button[type="submit"]' ).prop( 'disabled', false ).text( 'Save Configuration' );
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
						if ( confirm( sndp_admin.strings.copied + ' Edit now?' ) ) {
							window.location.href = response.data.edit_url;
						}
					} else {
						alert( response.data.message || sndp_admin.strings.error );
					}
				},
				error: function() {
					alert( sndp_admin.strings.error );
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
					} else {
						alert( response.data.message || sndp_admin.strings.error );
						$toggle.prop( 'checked', ! $toggle.prop( 'checked' ) );
					}
				},
				error: function() {
					alert( sndp_admin.strings.error );
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
						alert( response.data.message || sndp_admin.strings.error );
					}
				},
				error: function() {
					alert( sndp_admin.strings.error );
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
						alert( response.data.message || sndp_admin.strings.error );
					}
				},
				error: function() {
					alert( sndp_admin.strings.error );
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
					page_ids: $( '#sndp-snippet-page-ids' ).val()
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
						$submitBtn.text( 'Saved!' );
						setTimeout( function() {
							$submitBtn.text( 'Update Snippet' );
						}, 1500 );

						// Show warnings if any.
						$( '.sndp-code-warnings' ).remove();
						if ( response.data.warnings_html ) {
							$( '.sndp-code-field' ).after( response.data.warnings_html );
						}

						// Show shortcode hint if location is shortcode.
						SNDP_Admin.toggleConditionalOptions();
					} else {
						alert( response.data.message || sndp_admin.strings.error );
					}
				},
				error: function() {
					alert( sndp_admin.strings.error );
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

		closeModal: function() {
			$( '.sndp-modal' ).removeClass( 'sndp-modal-open' );
		}
	};

	// Initialize on document ready.
	$( document ).ready( function() {
		SNDP_Admin.init();
	} );

} )( jQuery );
