jQuery(document).ready(function($) {
    var $btn = $('#sejm-api-run-import');
    var $progressContainer = $('#sejm-api-progress-container');
    var $progressFill = $('#sejm-api-progress-fill');
    var $progressText = $('#sejm-api-progress-text');
    var $log = $('#sejm-api-log');
    
    var isRunning = false;

    $btn.on('click', function(e) {
        e.preventDefault();
        if (isRunning) return;

        if (!confirm('Czy na pewno chcesz uruchomić import? Może to potrwać kilka minut.')) {
            return;
        }

        startImport();
    });

    function startImport() {
        isRunning = true;
        $btn.hide();
        $progressContainer.css('display', 'block');
        
        $progressFill.css('width', '0%');
        $progressText.text('Pobieranie listy...');
        $log.empty().append('<p>Pobieranie listy posłów z API...</p>');

        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'sejm_import_start',
                nonce: sejmApiImport.nonce
            },
            success: function(response) {
                if (response.success) {
                    var total = response.data.total;
                    log('Lista pobrana. Do przetworzenia: ' + total + ' posłów.');
                    processNext(0, total);
                } else {
                    error('Błąd startu: ' + (response.data || 'Nieznany błąd'));
                    resetUI();
                }
            },
            error: function() {
                error('Błąd połączenia (Start).');
                resetUI();
            }
        });
    }

    function processNext(offset, total) {
        if (offset >= total) {
            finishImport();
            return;
        }

        updateProgress(offset, total);

        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'sejm_import_process',
                offset: offset,
                nonce: sejmApiImport.nonce
            },
            success: function(response) {
                if (response.success) {
                    processNext(offset + 1, total);
                } else {
                    error('Błąd przy pozycji ' + offset + ': ' + (response.data || 'Nieznany błąd'));
                    processNext(offset + 1, total);
                }
            },
            error: function() {
                console.log('Retry ' + offset);
                setTimeout(function() {
                     processNext(offset, total);
                }, 1000);
            }
        });
    }

    function finishImport() {
        updateProgress(100, 100);
        log('<strong>Import zakończony!</strong>');
        $progressText.text('Zakończono sukcesem!');
        
        $('#sejm-api-acf-notice').show();
        isRunning = false;
    }

    function updateProgress(current, total) {
        var percent = Math.round((current / total) * 100);
        $progressFill.css('width', percent + '%');
        $progressText.text(current + ' / ' + total + ' (' + percent + '%)');
    }

    function log(msg) {
        $log.append('<p>' + msg + '</p>');
        $log.scrollTop($log[0].scrollHeight);
    }

    function error(msg) {
        $log.append('<p style="color:red;">' + msg + '</p>');
    }

    function resetUI() {
        isRunning = false;
        $btn.show();
        $progressContainer.hide();
    }

});
