/**
 * Script para Chat Workspaces do Meilisearch
 * 
 * @package Meilisearch
 */

(function ($) {
    'use strict';

    $(document).ready(function () {
        // Alternar campos de provider
        $('#provider').on('change', function () {
            var selectedProvider = $(this).val();

            // Esconder todos os campos e remover required
            $('.provider-fields').removeClass('active').hide();
            $('.provider-fields input, .provider-fields select, .provider-fields textarea').prop('required', false);

            if (selectedProvider) {
                // Mostrar apenas os campos do provider selecionado
                $('.provider-' + selectedProvider).addClass('active').show();

                // Atualizar campos required apenas nos campos visíveis do provider selecionado
                $('.provider-' + selectedProvider + ' input[data-required="true"]').prop('required', true);
                $('.provider-' + selectedProvider + ' select[data-required="true"]').prop('required', true);
            }
        }).trigger('change');

        // Validação do formulário
        $('form').on('submit', function (e) {
            var selectedProvider = $('#provider').val();

            if (!selectedProvider) {
                alert('Por favor, selecione um provider.');
                e.preventDefault();
                return false;
            }

            var workspaceUid = $('#workspace_uid').val();
            if (workspaceUid && !/^[a-zA-Z0-9_-]+$/.test(workspaceUid)) {
                alert('UID do workspace deve conter apenas letras, números, hífens e underscores.');
                e.preventDefault();
                return false;
            }

            return true;
        });

        // Preview do system prompt
        $('#system_prompt').on('input', function () {
            var length = $(this).val().length;
            var maxLength = 2000;

            if (length > maxLength) {
                $(this).val($(this).val().substring(0, maxLength));
                alert('System prompt não pode exceder ' + maxLength + ' caracteres.');
            }
        });
    });

})(jQuery);
