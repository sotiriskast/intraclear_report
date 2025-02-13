{{--
/**
 * Base Email Layout
 *
 * This layout provides:
 * - Consistent styling for all system emails
 * - Responsive design for various email clients
 * - Sections for title, content, and footer
 * - Standard branding elements
 *
 * Sections:
 * @section title Email subject/title
 * @section content Main email content
 * @section footer Email footer content
 */
--}}
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <style>
        @media only screen and (max-width: 600px) {
            .inner-body {
                width: 100% !important;
            }
        }

        body {
            background-color: #f8fafc;
            color: #2d3748;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }

        .container {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin: 40px auto;
            max-width: 600px;
            padding: 20px;
        }

        .header {
            text-align: center;
            padding: 20px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .logo {
            max-height: 50px;
            margin-bottom: 10px;
        }

        .content {
            padding: 20px 0;
        }

        .info-box {
            background-color: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            margin: 20px 0;
            padding: 15px;
        }

        .footer {
            border-top: 1px solid #e2e8f0;
            color: #718096;
            font-size: 0.875rem;
            padding-top: 20px;
            text-align: center;
        }

        .button {
            background-color: #4f46e5;
            border-radius: 4px;
            color: #ffffff;
            display: inline-block;
            font-weight: 600;
            padding: 12px 24px;
            text-decoration: none;
            text-align: center;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
{{--        <a href="/">--}}
{{--            <div class="bg-blue-500  flex justify-center items-center">--}}
{{--                <img src="{{ asset('images/logo.png') }}"--}}
{{--                     alt="Logo"--}}
{{--                    width="250px"--}}
{{--                     height="176px"--}}
{{--                >--}}
{{--            </div>--}}
{{--        </a>--}}
        <h1>@yield('title')</h1>
    </div>

    <div class="content">
        @yield('content')
    </div>

    <div class="footer">
        <p>© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        @yield('footer')
    </div>
</div>
</body>
</html>
