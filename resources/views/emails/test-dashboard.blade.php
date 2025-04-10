<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Test Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .email-card {
            transition: transform 0.2s;
        }
        .email-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .result-box {
            min-height: 100px;
            max-height: 200px;
            overflow-y: auto;
            display: none;
        }
    </style>
</head>
<body>
<div class="container py-5">
    <h1 class="mb-4">Email Test Dashboard</h1>
    <p class="lead mb-4">
        Use this dashboard to test email notifications from the application.
        <br>Make sure your <code>.env</code> file has proper email settings.
    </p>

    @if(empty(config('app.admin_email')) || !filter_var(config('app.admin_email'), FILTER_VALIDATE_EMAIL))
        <div class="alert alert-danger mb-4">
            <strong>Warning:</strong> No valid admin email is configured!
            <br>Please set <code>APP_ADMIN_EMAIL=your-email@example.com</code> in your <code>.env</code> file.
            <br>Testing emails will fail until this is properly configured.
        </div>
    @else
        <div class="alert alert-info mb-4">
            <strong>Note:</strong> Emails will be sent to the admin email defined in your configuration:
            <code>{{ config('app.admin_email') }}</code>
        </div>
    @endif

    <div class="row">
        @foreach($emails as $email)
            <div class="col-md-6 mb-4">
                <div class="card email-card h-100">
                    <div class="card-body">
                        <h5 class="card-title">{{ $email['name'] }}</h5>
                        <p class="card-text">{{ $email['description'] }}</p>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary send-email-btn" data-url="{{ $email['url'] }}" data-name="{{ $email['name'] }}">
                            Send Test Email
                        </button>
                        @if(isset($email['preview_url']))
                            <a href="{{ $email['preview_url'] }}" target="_blank" class="btn btn-outline-secondary ms-2">
                                Preview Template
                            </a>
                        @endif
                    </div>
                    <div class="card-footer bg-light result-box" id="result-{{ Str::slug($email['name']) }}">
                        <div class="spinner-border text-primary d-none" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <pre class="response-data"></pre>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const buttons = document.querySelectorAll('.send-email-btn');

        buttons.forEach(button => {
            button.addEventListener('click', function() {
                const url = this.getAttribute('data-url');
                const name = this.getAttribute('data-name');
                const resultBox = document.getElementById('result-' + name.toLowerCase().replace(/\s+/g, '-'));

                // Show the result box and spinner
                resultBox.style.display = 'block';
                const spinner = resultBox.querySelector('.spinner-border');
                spinner.classList.remove('d-none');

                const responseData = resultBox.querySelector('.response-data');
                responseData.textContent = '';

                // Disable the button
                this.disabled = true;
                this.innerHTML = 'Sending...';

                fetch(url)
                    .then(response => response.json())
                    .then(data => {
                        responseData.textContent = JSON.stringify(data, null, 2);
                        spinner.classList.add('d-none');

                        // Re-enable the button
                        this.disabled = false;
                        this.innerHTML = 'Send Test Email';
                    })
                    .catch(error => {
                        responseData.textContent = 'Error: ' + error.message;
                        spinner.classList.add('d-none');

                        // Re-enable the button
                        this.disabled = false;
                        this.innerHTML = 'Send Test Email';
                    });
            });
        });
    });
</script>
</body>
</html>
