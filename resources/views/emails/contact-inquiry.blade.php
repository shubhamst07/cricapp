<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Contact Inquiry - LiveCricket</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e293b;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f1f5f9; padding: 30px 15px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" style="max-width: 600px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05); border: 1px solid #e2e8f0;" cellspacing="0" cellpadding="0">
                    <!-- Brand Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #0b1120 0%, #1e1b4b 100%); padding: 30px; text-align: center;">
                            <div style="display: inline-block; padding: 8px 16px; border-radius: 999px; background-color: rgba(99, 102, 241, 0.2); border: 1px solid rgba(99, 102, 241, 0.4); margin-bottom: 12px;">
                                <span style="font-size: 11px; font-weight: 800; color: #a5b4fc; text-transform: uppercase; letter-spacing: 1px;">📬 New Website Message</span>
                            </div>
                            <h1 style="margin: 0; font-size: 24px; font-weight: 900; color: #ffffff; letter-spacing: -0.5px;">
                                Live<span style="color: #818cf8;">Cricket</span>
                            </h1>
                            <p style="margin: 6px 0 0; font-size: 13px; color: #94a3b8;">
                                Contact Form Submission Notification
                            </p>
                        </td>
                    </tr>

                    <!-- Body Content -->
                    <tr>
                        <td style="padding: 30px;">
                            <p style="margin: 0 0 20px; font-size: 15px; line-height: 1.5; color: #334155;">
                                Hello <strong>Shubham</strong>, someone has sent a new inquiry through the LiveCricket contact form. Here are the details:
                            </p>

                            <!-- Summary Info Box -->
                            <table role="presentation" width="100%" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 24px;" cellspacing="0" cellpadding="12">
                                <tr>
                                    <td width="35%" style="font-size: 12px; font-weight: bold; color: #64748b; text-transform: uppercase; border-bottom: 1px solid #edf2f7;">Sender Name</td>
                                    <td style="font-size: 14px; font-weight: 800; color: #0f172a; border-bottom: 1px solid #edf2f7;">{{ $name }}</td>
                                </tr>
                                <tr>
                                    <td style="font-size: 12px; font-weight: bold; color: #64748b; text-transform: uppercase; border-bottom: 1px solid #edf2f7;">Email Address</td>
                                    <td style="font-size: 14px; color: #4338ca; border-bottom: 1px solid #edf2f7;">
                                        <a href="mailto:{{ $email }}" style="color: #4338ca; text-decoration: none; font-weight: 600;">{{ $email }}</a>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-size: 12px; font-weight: bold; color: #64748b; text-transform: uppercase; border-bottom: 1px solid #edf2f7;">Phone Number</td>
                                    <td style="font-size: 14px; color: #0f172a; border-bottom: 1px solid #edf2f7;">
                                        @if(!empty($phone) && $phone !== 'Not provided')
                                            <a href="tel:{{ $phone }}" style="color: #059669; text-decoration: none; font-weight: 700;">{{ $phone }}</a>
                                            <span style="font-size: 11px; color: #64748b; margin-left: 6px;">(Click to call)</span>
                                        @else
                                            <span style="color: #94a3b8; font-style: italic;">Not provided</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-size: 12px; font-weight: bold; color: #64748b; text-transform: uppercase; border-bottom: 1px solid #edf2f7;">Inquiry Topic</td>
                                    <td style="font-size: 13px; font-weight: 700; color: #0f172a; border-bottom: 1px solid #edf2f7;">
                                        <span style="display: inline-block; padding: 3px 8px; border-radius: 6px; background-color: #e0e7ff; color: #3730a3; font-size: 12px;">
                                            {{ $inquiryType }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-size: 12px; font-weight: bold; color: #64748b; text-transform: uppercase;">Submitted At</td>
                                    <td style="font-size: 12px; color: #64748b; font-family: monospace;">{{ $submittedAt }}</td>
                                </tr>
                            </table>

                            <!-- Message Body -->
                            <div style="margin-bottom: 25px;">
                                <h3 style="margin: 0 0 10px; font-size: 13px; font-weight: 800; text-transform: uppercase; color: #475569; letter-spacing: 0.5px;">
                                    Message Content:
                                </h3>
                                <div style="background-color: #ffffff; border-left: 4px solid #6366f1; padding: 16px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); font-size: 14px; line-height: 1.6; color: #1e293b; white-space: pre-line;">{{ $messageBody }}</div>
                            </div>

                            <!-- Reply CTA Button -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top: 30px;">
                                <tr>
                                    <td align="center">
                                        <a href="mailto:{{ $email }}?subject={{ urlencode('Re: LiveCricket Inquiry - ' . $inquiryType) }}" 
                                           style="display: inline-block; padding: 14px 28px; background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%); color: #ffffff; text-decoration: none; font-weight: 800; font-size: 14px; border-radius: 12px; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.35);">
                                            Reply to {{ $name }} ✉️
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8fafc; padding: 20px; text-align: center; border-top: 1px solid #e2e8f0; font-size: 11px; color: #94a3b8;">
                            This automated message was sent from the LiveCricket Contact Form.<br>
                            © {{ date('Y') }} LiveCricket • Platform Lead: Shubham Thakur
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
