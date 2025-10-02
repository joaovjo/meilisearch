/**
 * Meilisearch Autocomplete
 * 
 * Provides automatic autocomplete functionality for WordPress search.
 */
(function ($) {
    'use strict';

    // Configuration from WordPress
    const config = window.meilisearchConfig || {};
    const apiUrl = config.apiUrl || '/wp-json/meilisearch/v1/autocomplete';

    // Debounce function
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Initialize autocomplete on all search inputs
    function initAutocomplete() {
        const searchInputs = $('input[type="search"], input[name="s"]');

        searchInputs.each(function () {
            const $input = $(this);

            // Skip if already initialized
            if ($input.data('meilisearch-initialized')) {
                return;
            }

            $input.data('meilisearch-initialized', true);

            // Create autocomplete container
            const $container = $('<div>')
                .addClass('meilisearch-autocomplete')
                .insertAfter($input);

            // Create results list
            const $results = $('<ul>')
                .addClass('meilisearch-autocomplete-results')
                .appendTo($container)
                .hide();

            // Handle input with debounce
            $input.on('input', debounce(function () {
                const query = $(this).val().trim();

                if (query.length < 2) {
                    $results.hide().empty();
                    return;
                }

                // Fetch suggestions
                fetchSuggestions(query, $results);
            }, 300));

            // Handle keyboard navigation
            $input.on('keydown', function (e) {
                const $items = $results.find('li');
                const $active = $items.filter('.active');

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if ($active.length === 0) {
                        $items.first().addClass('active');
                    } else {
                        $active.removeClass('active').next().addClass('active');
                    }
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if ($active.length > 0) {
                        $active.removeClass('active').prev().addClass('active');
                    }
                } else if (e.key === 'Enter' && $active.length > 0) {
                    e.preventDefault();
                    window.location.href = $active.find('a').attr('href');
                } else if (e.key === 'Escape') {
                    $results.hide();
                }
            });

            // Close on click outside
            $(document).on('click', function (e) {
                if (!$input.is(e.target) && !$container.is(e.target) && $container.has(e.target).length === 0) {
                    $results.hide();
                }
            });
        });
    }

    // Fetch suggestions from API
    function fetchSuggestions(query, $results) {
        $.ajax({
            url: apiUrl,
            method: 'GET',
            data: {
                q: query,
                limit: 5
            },
            beforeSend: function (xhr) {
                if (config.nonce) {
                    xhr.setRequestHeader('X-WP-Nonce', config.nonce);
                }
                $results.html('<li class="loading">Searching...</li>').show();
            },
            success: function (suggestions) {
                $results.empty();

                if (!suggestions || suggestions.length === 0) {
                    $results.html('<li class="no-results">No results found</li>');
                    return;
                }

                suggestions.forEach(function (item) {
                    const $item = $('<li>')
                        .append(
                            $('<a>')
                                .attr('href', item.permalink)
                                .html(
                                    '<strong>' + escapeHtml(item.title) + '</strong>' +
                                    (item.excerpt ? '<span class="excerpt">' + escapeHtml(item.excerpt) + '</span>' : '')
                                )
                        );

                    $results.append($item);
                });

                // Handle hover
                $results.find('li').on('mouseenter', function () {
                    $(this).addClass('active').siblings().removeClass('active');
                });

                $results.show();
            },
            error: function () {
                $results.html('<li class="error">Error loading suggestions</li>');
            }
        });
    }

    // Escape HTML to prevent XSS
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    // Initialize on document ready
    $(document).ready(function () {
        initAutocomplete();
    });

})(jQuery);
