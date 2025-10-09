/**
 * Script para Busca Federada do Meilisearch
 * 
 * @package Meilisearch
 */

(function ($) {
    'use strict';

    $(document).ready(function () {
        // Handler do formulário de busca federada
        $('#federated-search-form').on('submit', function (e) {
            e.preventDefault();

            var selectedIndexes = [];
            $('.federated-index:checked').each(function () {
                selectedIndexes.push($(this).val());
            });

            if (selectedIndexes.length === 0) {
                alert('Por favor, selecione pelo menos um índice.');
                return false;
            }

            var query = $('#search_query').val();
            if (!query || query.trim() === '') {
                alert('Por favor, digite um termo de busca.');
                return false;
            }

            performFederatedSearch(selectedIndexes, query);
        });

        // Função para executar busca federada
        function performFederatedSearch(indexes, query) {
            var $form = $('#federated-search-form');
            var $spinner = $form.find('.spinner');
            var $results = $('#federated-results');
            var $resultsContent = $('#results-content');

            $spinner.addClass('is-active');
            $results.hide();
            $resultsContent.empty();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'meilisearch_federated_search',
                    nonce: meilisearchFederated.nonce,
                    indexes: indexes,
                    query: query,
                    limit: $('#limit').val(),
                    federation_limit: $('#federation_limit').val()
                },
                success: function (response) {
                    $spinner.removeClass('is-active');

                    if (response.success && response.data) {
                        displayResults(response.data);
                        $results.show();
                    } else {
                        var errorMsg = response.data && response.data.message
                            ? response.data.message
                            : 'Erro desconhecido ao realizar a busca.';
                        alert('Erro: ' + errorMsg);
                    }
                },
                error: function (xhr, status, error) {
                    $spinner.removeClass('is-active');
                    alert('Erro na requisição: ' + error);
                }
            });
        }

        // Função para exibir resultados
        function displayResults(data) {
            var $resultsContent = $('#results-content');
            var html = '';

            // Estatísticas
            html += '<div class="federated-stats" style="background: #f0f0f1; padding: 15px; margin-bottom: 20px; border-radius: 4px;">';
            html += '<p><strong>Total de resultados:</strong> ' + (data.hits ? data.hits.length : 0) + '</p>';
            html += '<p><strong>Tempo de processamento:</strong> ' + (data.processingTimeMs || 0) + 'ms</p>';
            html += '</div>';

            if (!data.hits || data.hits.length === 0) {
                html += '<p style="text-align: center; padding: 30px; color: #666;">Nenhum resultado encontrado.</p>';
            } else {
                data.hits.forEach(function (hit) {
                    html += '<div class="result-item">';
                    html += '<div class="result-item-header">';

                    if (hit._federation && hit._federation.indexUid) {
                        html += '<span class="result-index-badge">' + escapeHtml(hit._federation.indexUid) + '</span>';
                    }

                    if (hit._rankingScore !== undefined) {
                        html += '<span class="result-score">Score: ' + hit._rankingScore.toFixed(4) + '</span>';
                    }

                    html += '</div>';
                    html += '<div class="result-content">';

                    var title = hit.title || hit.name || hit.id || 'Sem título';
                    html += '<strong style="font-size: 16px;">' + escapeHtml(title) + '</strong><br>';

                    if (hit.content) {
                        var content = hit.content.substring(0, 200);
                        if (hit.content.length > 200) {
                            content += '...';
                        }
                        html += '<p style="margin-top: 10px; color: #666;">' + escapeHtml(content) + '</p>';
                    }

                    if (hit.url) {
                        html += '<p><a href="' + escapeHtml(hit.url) + '" target="_blank" style="color: #2271b1;">Ver mais →</a></p>';
                    }

                    html += '</div>';
                    html += '</div>';
                });
            }

            $resultsContent.html(html);
        }

        // Função para escapar HTML
        function escapeHtml(text) {
            if (!text) return '';
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, function (m) { return map[m]; });
        }

        // Selecionar/desselecionar todos os índices
        $('#select-all-indexes').on('click', function (e) {
            e.preventDefault();
            $('.federated-index').prop('checked', true);
        });

        $('#deselect-all-indexes').on('click', function (e) {
            e.preventDefault();
            $('.federated-index').prop('checked', false);
        });
    });

})(jQuery);
