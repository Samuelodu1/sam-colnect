<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HTML Element Counter</title>
    <link href="assets/style.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body>
  
<div class="container">
    <h1 class="page-title">HTML Element Counter</h1>
    <p class="page-subtitle">Count HTML elements on any webpage</p>

    <form id="counterForm" class="form-card">
        <div class="form-content">
            <div class="form-group">
                <label class="form-label">Enter URL</label>
                <input type="url" name="url" placeholder="https://example.com" required class="form-input">
            </div>
            <div class="form-group">
                <label class="form-label">HTML Element</label>
                <input type="text" name="element" placeholder="img, div, p, a" required class="form-input">
            </div>
            <button type="submit" class="submit-button">Check Elements</button>
        </div>
    </form>

    <div id="result"></div>

    <!-- Removed the Erin Lindford profile section as it doesn't seem relevant to the element counter functionality -->
</div>

<script>
$(function(){
    $('#counterForm').on('submit', function(e){
        e.preventDefault();
        const url = $('[name="url"]').val().trim();
        const element = $('[name="element"]').val().trim();

        if(!url || !element){
            $('#result').html('<div class="error-message">Please enter both fields.</div>');
            return;
        }

        $('#result').html('<div class="loading-message">Loading...</div>');
        const $btn = $(this).find('button').prop('disabled', true).text('Checking...');

        $.ajax({
            url: 'colnect.php',
            type: 'POST',
            data: { url: url, element: element },
            dataType: 'json',
            success: function(res){
                $btn.prop('disabled', false).text('Check Elements');
                
                if(res?.success){
                    const s = res.stats || {};
                    $('#result').html(`
                        <div class="result-card">
                            <div class="result-header">Results</div>
                            <div class="result-content">
                                <div class="result-row"><span>URL:</span><span class="break-all">${res.url}</span></div>
                                <div class="result-row"><span>Fetched:</span><span>${res.fetched}</span></div>
                                <div class="result-row"><span>Duration:</span><span>${res.duration}ms</span></div>
                                <div class="result-row"><span>Count:</span><span class="font-bold">${res.count} ${element} elements</span></div>
                            </div>
                        </div>
                        <div class="stats-card">
                            <div class="stats-header">Statistics</div>
                            <div class="stats-grid">
                                <div class="stat-item">
                                    <div class="stat-number">${s.domain_urls??0}</div>
                                    <div class="stat-label">Domain URLs</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number">${s.avg_duration??0}</div>
                                    <div class="stat-label">Avg Time</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number">${s.domain_total??0}</div>
                                    <div class="stat-label">Domain Total</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number">${s.all_total??0}</div>
                                    <div class="stat-label">All Total</div>
                                </div>
                            </div>
                        </div>
                    `);
                } else {
                    $('#result').html(`<div class="error-message">${res?.message||'Error'}</div>`);
                }
            },
            error: function(){
                $btn.prop('disabled', false).text('Check Elements');
                $('#result').html('<div class="error-message">Server error</div>');
            }
        });
    });
});
</script>
</body>
</html>