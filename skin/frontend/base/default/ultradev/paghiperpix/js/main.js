var UltraDevPagHiperPix = (function () {
    'use strict';

    var config = {
        checkStatusUrl: null,
        orderId: null,
        pollingInterval: 6
    };

    var pollTimer = null;

    function $(id) {
        return document.getElementById(id);
    }

    function copyEmv() {
        var input = $('paghiperpix-emv-input');
        var feedback = $('paghiperpix-copy-feedback');

        if (!input) {
            return;
        }

        input.select();
        input.setSelectionRange(0, 99999);

        var copied = false;

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(input.value).then(function () {
                showCopyFeedback(feedback, true);
            }).catch(function () {
                copied = document.execCommand('copy');
                showCopyFeedback(feedback, copied);
            });
        } else {
            copied = document.execCommand('copy');
            showCopyFeedback(feedback, copied);
        }
    }

    function showCopyFeedback(feedback, success) {
        if (!feedback) {
            return;
        }
        feedback.textContent = success ? 'Código copiado!' : 'Não foi possível copiar automaticamente.';
        feedback.className = 'paghiperpix-copy-feedback ' + (success ? 'is-success' : 'is-error');

        window.setTimeout(function () {
            feedback.textContent = '';
        }, 4000);
    }

    function checkStatus() {
        if (!config.checkStatusUrl || !config.orderId) {
            return;
        }

        var url = config.checkStatusUrl + (config.checkStatusUrl.indexOf('?') === -1 ? '?' : '&') + 'order_id=' + encodeURIComponent(config.orderId);

        var xhr = new XMLHttpRequest();
        xhr.open('GET', url, true);
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) {
                return;
            }

            if (xhr.status !== 200) {
                return; // tenta novamente no próximo ciclo
            }

            try {
                var data = JSON.parse(xhr.responseText);
                if (data && data.paid) {
                    onPaid();
                }
            } catch (e) {
                // ignora resposta malformada, tenta novamente
            }
        };
        xhr.send();
    }

    function onPaid() {
        if (pollTimer) {
            window.clearInterval(pollTimer);
            pollTimer = null;
        }

        var pending = $('paghiperpix-pending');
        var paid = $('paghiperpix-paid');

        if (pending) {
            pending.style.display = 'none';
        }
        if (paid) {
            paid.style.display = 'block';
        }
    }

    function init(options) {
        config = Object.assign({}, config, options || {});

        var copyBtn = $('paghiperpix-copy-btn');
        if (copyBtn) {
            copyBtn.addEventListener('click', copyEmv);
        }

        if (config.checkStatusUrl && config.orderId) {
            var intervalMs = Math.max(3, parseInt(config.pollingInterval, 10) || 6) * 1000;
            pollTimer = window.setInterval(checkStatus, intervalMs);
            // primeira checagem logo após alguns segundos, sem esperar o ciclo completo
            window.setTimeout(checkStatus, 3000);
        }
    }

    return {
        init: init
    };
})();
