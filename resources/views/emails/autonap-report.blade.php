<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoNAP Report</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.1);">

                    {{-- Header --}}
                    <tr>
                        <td style="background-color:#0f766e;padding:20px 32px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="vertical-align:middle;">
                                        <span style="color:#ffffff;font-size:18px;font-weight:700;">AutoNAP Report</span>
                                    </td>
                                    <td style="text-align:right;vertical-align:middle;">
                                        <span style="color:rgba(255,255,255,0.8);font-size:13px;">{{ $report['formType'] }} &mdash; {{ $report['site'] }}</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Status Banner --}}
                    <tr>
                        <td style="padding:0;">
                            @if($report['failed'] === 0)
                                <div style="padding:14px 32px;color:#111827;font-size:14px;">
                                    Job completed &mdash; All {{ $report['success'] }} records saved successfully
                                </div>
                            @elseif($report['success'] === 0)
                                <div style="padding:14px 32px;color:#111827;font-size:14px;">
                                    Job failed &mdash; {{ $report['failed'] }}/{{ $report['total'] }} records failed
                                </div>
                            @else
                                <div style="padding:14px 32px;color:#111827;font-size:14px;">
                                    Partial success &mdash; {{ $report['success'] }} saved, {{ $report['failed'] }} failed
                                </div>
                            @endif
                        </td>
                    </tr>

                    {{-- Summary Cards --}}
                    <tr>
                        <td style="padding:24px 32px 0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td width="33%" style="padding:0 6px 0 0;">
                                        <div style="background-color:#f0fdfa;border:1px solid #ccfbf1;border-radius:8px;padding:16px;text-align:center;">
                                            <div style="font-size:28px;font-weight:700;color:#0f766e;">{{ $report['total'] }}</div>
                                            <div style="font-size:11px;color:#5f6368;text-transform:uppercase;letter-spacing:0.5px;margin-top:4px;">Total</div>
                                        </div>
                                    </td>
                                    <td width="33%" style="padding:0 3px;">
                                        <div style="background-color:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:16px;text-align:center;">
                                            <div style="font-size:28px;font-weight:700;color:#16a34a;">{{ $report['success'] }}</div>
                                            <div style="font-size:11px;color:#5f6368;text-transform:uppercase;letter-spacing:0.5px;margin-top:4px;">Success</div>
                                        </div>
                                    </td>
                                    <td width="33%" style="padding:0 0 0 6px;">
                                        <div style="background-color:{{ $report['failed'] > 0 ? '#fef2f2' : '#f9fafb' }};border:1px solid {{ $report['failed'] > 0 ? '#fecaca' : '#e5e7eb' }};border-radius:8px;padding:16px;text-align:center;">
                                            <div style="font-size:28px;font-weight:700;color:{{ $report['failed'] > 0 ? '#dc2626' : '#9ca3af' }};">{{ $report['failed'] }}</div>
                                            <div style="font-size:11px;color:#5f6368;text-transform:uppercase;letter-spacing:0.5px;margin-top:4px;">Failed</div>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Job Details --}}
                    <tr>
                        <td style="padding:24px 32px 0;">
                            <h2 style="font-size:14px;font-weight:600;color:#374151;margin:0 0 12px;text-transform:uppercase;letter-spacing:0.5px;">Job Details</h2>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
                                <tr style="background-color:#f9fafb;">
                                    <td style="padding:10px 16px;font-size:13px;color:#6b7280;width:160px;border-bottom:1px solid #e5e7eb;">Job ID</td>
                                    <td style="padding:10px 16px;font-size:13px;color:#111827;font-family:monospace;border-bottom:1px solid #e5e7eb;">{{ $report['jobId'] }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 16px;font-size:13px;color:#6b7280;width:160px;border-bottom:1px solid #e5e7eb;">Site</td>
                                    <td style="padding:10px 16px;font-size:13px;color:#111827;border-bottom:1px solid #e5e7eb;">{{ $report['site'] }}</td>
                                </tr>
                                <tr style="background-color:#f9fafb;">
                                    <td style="padding:10px 16px;font-size:13px;color:#6b7280;width:160px;border-bottom:1px solid #e5e7eb;">Form Type</td>
                                    <td style="padding:10px 16px;font-size:13px;color:#111827;border-bottom:1px solid #e5e7eb;">
                                        <span style="display:inline-block;background-color:{{ $report['formType'] === 'VCT' ? '#dbeafe' : '#fae8ff' }};color:{{ $report['formType'] === 'VCT' ? '#1d4ed8' : '#a21caf' }};padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600;">{{ $report['formType'] }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 16px;font-size:13px;color:#6b7280;width:160px;border-bottom:1px solid #e5e7eb;">NAP Username</td>
                                    <td style="padding:10px 16px;font-size:13px;color:#111827;border-bottom:1px solid #e5e7eb;">{{ $report['napDisplayName'] ?: '-' }}</td>
                                </tr>
                                <tr style="background-color:#f9fafb;">
                                    <td style="padding:10px 16px;font-size:13px;color:#6b7280;width:160px;border-bottom:1px solid #e5e7eb;">Started</td>
                                    <td style="padding:10px 16px;font-size:13px;color:#111827;border-bottom:1px solid #e5e7eb;">{{ $report['startedAt'] }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 16px;font-size:13px;color:#6b7280;width:160px;border-bottom:1px solid #e5e7eb;">Finished</td>
                                    <td style="padding:10px 16px;font-size:13px;color:#111827;border-bottom:1px solid #e5e7eb;">{{ $report['finishedAt'] }}</td>
                                </tr>
                                <tr style="background-color:#f9fafb;">
                                    <td style="padding:10px 16px;font-size:13px;color:#6b7280;width:160px;border-bottom:1px solid #e5e7eb;">Duration</td>
                                    <td style="padding:10px 16px;font-size:13px;color:#111827;border-bottom:1px solid #e5e7eb;">
                                        @php
                                            $mins = floor($report['durationSeconds'] / 60);
                                            $secs = $report['durationSeconds'] % 60;
                                        @endphp
                                        {{ $mins > 0 ? $mins . ' min ' : '' }}{{ $secs }} sec
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 16px;font-size:13px;color:#6b7280;width:160px;">Avg / Record</td>
                                    <td style="padding:10px 16px;font-size:13px;color:#111827;">{{ number_format($report['avgSecondsPerRecord'], 1) }} sec</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Results Table --}}
                    <tr>
                        <td style="padding:24px 32px 0;">
                            <h2 style="font-size:14px;font-weight:600;color:#374151;margin:0 0 12px;text-transform:uppercase;letter-spacing:0.5px;">Record Results</h2>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;font-size:12px;">
                                <thead>
                                    <tr style="background-color:#f9fafb;">
                                        <th style="padding:10px 12px;text-align:center;color:#6b7280;font-weight:600;border-bottom:2px solid #e5e7eb;width:36px;">#</th>
                                        <th style="padding:10px 12px;text-align:left;color:#6b7280;font-weight:600;border-bottom:2px solid #e5e7eb;">UIC</th>
                                        <th style="padding:10px 12px;text-align:left;color:#6b7280;font-weight:600;border-bottom:2px solid #e5e7eb;">PID</th>
                                        <th style="padding:10px 12px;text-align:center;color:#6b7280;font-weight:600;border-bottom:2px solid #e5e7eb;">Status</th>
                                        <th style="padding:10px 12px;text-align:left;color:#6b7280;font-weight:600;border-bottom:2px solid #e5e7eb;">NAP Code / Error</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($report['results'] as $i => $row)
                                        <tr style="background-color:{{ $i % 2 === 0 ? '#ffffff' : '#f9fafb' }};">
                                            <td style="padding:8px 12px;text-align:center;color:#6b7280;border-bottom:1px solid #f3f4f6;">{{ $i + 1 }}</td>
                                            <td style="padding:8px 12px;color:#374151;border-bottom:1px solid #f3f4f6;">{{ $row['uic'] ?? '-' }}</td>
                                            <td style="padding:8px 12px;color:#374151;font-family:monospace;border-bottom:1px solid #f3f4f6;">{{ Str::mask($row['id_card'] ?? '', '*', 0, 9) }}</td>
                                            <td style="padding:8px 12px;text-align:center;border-bottom:1px solid #f3f4f6;">
                                                @if($row['success'])
                                                    <span style="display:inline-block;background-color:#dcfce7;color:#16a34a;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;">OK</span>
                                                @else
                                                    <span style="display:inline-block;background-color:#fee2e2;color:#dc2626;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;">FAIL</span>
                                                @endif
                                            </td>
                                            <td style="padding:8px 12px;border-bottom:1px solid #f3f4f6;{{ $row['success'] ? 'color:#374151;' : 'color:#dc2626;' }}">
                                                @if($row['success'])
                                                    {{ $row['nap_code'] ?? '-' }}
                                                @else
                                                    {{ $row['error'] ?? 'Unknown error' }}
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:24px 32px 28px;">
                            <div style="border-top:1px solid #e5e7eb;padding-top:16px;text-align:center;">
                                <p style="font-size:12px;color:#9ca3af;margin:0;">
                                    AutoNAP &mdash; Automated NAP Plus Recording System
                                </p>
                                <p style="font-size:11px;color:#d1d5db;margin:6px 0 0;">
                                    This is an automated report. Do not reply to this email.
                                </p>
                            </div>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
