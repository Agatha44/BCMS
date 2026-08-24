<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? 'BMS Notification' }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:Arial,Helvetica,sans-serif;">
@php
    $compact = $compact ?? false;
    $brandMaroon = '#802323';
    $brandGold = '#FDB913';
    $brandGoldLight = '#fff8e1';
    $statusVariant = $statusVariant ?? 'info';
    $statusColors = [
        'success' => ['bg' => $brandGold, 'text' => $brandMaroon],
        'warning' => ['bg' => '#fde68a', 'text' => '#78350f'],
        'danger'  => ['bg' => '#fecaca', 'text' => '#991b1b'],
        'info'    => ['bg' => '#f5e6e6', 'text' => $brandMaroon],
    ];
    $badge = $statusColors[$statusVariant] ?? $statusColors['info'];
    $cardWidth = $compact ? '440' : '560';
    $outerPad = $compact ? '12px 8px' : '24px 12px';
    $headerPad = $compact ? '12px 16px' : '20px 24px';
    $bodyPad = $compact ? '14px 16px 16px' : '18px 24px 22px';
    $footerPad = $compact ? '10px 16px' : '14px 24px';
@endphp
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f1f5f9;">
    <tr>
        <td align="center" style="padding:{{ $outerPad }};">
            <table role="presentation" width="{{ $cardWidth }}" cellspacing="0" cellpadding="0" border="0" style="max-width:{{ $cardWidth }}px;width:100%;background:#fff;border-radius:{{ $compact ? '6' : '8' }}px;border:1px solid #e2e8f0;">
                <tr>
                    <td style="background:{{ $brandMaroon }};padding:{{ $headerPad }};">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                            <tr>
                                <td>
                                    @if(!$compact)
                                    <p style="margin:0 0 2px;font-size:10px;font-weight:bold;letter-spacing:0.08em;text-transform:uppercase;color:rgba(255,255,255,0.75);">BMS</p>
                                    @endif
                                    <p style="margin:0;font-size:{{ $compact ? '15' : '18' }}px;font-weight:bold;color:#ffffff;line-height:1.3;">
                                        {{ $headline ?? $moduleTitle ?? 'Notification' }}
                                    </p>
                                    @if($compact && !empty($moduleTitle) && ($headline ?? '') !== ($moduleTitle ?? ''))
                                    <p style="margin:4px 0 0;font-size:11px;color:rgba(255,255,255,0.9);">{{ $moduleTitle }}</p>
                                    @endif
                                </td>
                                @if(!empty($statusLabel))
                                <td align="right" valign="middle" width="1">
                                    <span style="display:inline-block;padding:{{ $compact ? '3px 8px' : '4px 10px' }};font-size:{{ $compact ? '10' : '11' }}px;font-weight:bold;border-radius:12px;background:{{ $badge['bg'] }};color:{{ $badge['text'] }};white-space:nowrap;">
                                        {{ $statusLabel }}
                                    </span>
                                </td>
                                @endif
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="height:4px;background:{{ $brandGold }};font-size:0;line-height:0;">&nbsp;</td>
                </tr>
                <tr>
                    <td style="padding:{{ $bodyPad }};">
                        @if(!empty($greeting) || !empty($intro))
                        <p style="margin:0 0 {{ $compact ? '10' : '14' }}px;font-size:{{ $compact ? '13' : '14' }}px;line-height:1.5;color:#334155;">
                            @if(!empty($greeting)){{ $greeting }}@endif
                            @if(!empty($greeting) && !empty($intro))<br><br>@endif
                            @if(!empty($intro)){!! $intro !!}@endif
                        </p>
                        @endif

                        @yield('content')

                        @if(!empty($details) && is_array($details))
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 {{ $compact ? '10' : '14' }}px;font-size:{{ $compact ? '12' : '13' }}px;border:1px solid #e2e8f0;border-radius:4px;">
                            @foreach($details as $index => $row)
                            <tr>
                                <td style="padding:{{ $compact ? '6px 10px' : '8px 12px' }};color:#64748b;width:34%;background:{{ $brandGoldLight }};{{ !$loop->last ? 'border-bottom:1px solid #e2e8f0;' : '' }}">
                                    {{ $row['label'] ?? '' }}
                                </td>
                                <td style="padding:{{ $compact ? '6px 10px' : '8px 12px' }};color:#0f172a;font-weight:bold;{{ !$loop->last ? 'border-bottom:1px solid #e2e8f0;' : '' }}">
                                    {{ $row['value'] ?? '—' }}
                                </td>
                            </tr>
                            @endforeach
                        </table>
                        @endif

                        @if(!empty($note))
                        <p style="margin:0 0 {{ $compact ? '10' : '12' }}px;padding:{{ $compact ? '8px 10px' : '10px 12px' }};font-size:{{ $compact ? '11' : '12' }}px;line-height:1.45;color:{{ $brandMaroon }};background:{{ $brandGoldLight }};border-left:3px solid {{ $brandGold }};border-radius:4px;">
                            {{ $note }}
                        </p>
                        @endif

                        @if(!empty($warning))
                        <p style="margin:0 0 {{ $compact ? '10' : '12' }}px;padding:{{ $compact ? '8px 10px' : '10px 12px' }};font-size:{{ $compact ? '11' : '12' }}px;line-height:1.45;color:#92400e;background:#fffbeb;border-radius:4px;">
                            {{ $warning }}
                        </p>
                        @endif

                        @if(!empty($actionUrl) && !empty($actionLabel))
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:0;">
                            <tr>
                                <td style="border-radius:4px;background:{{ $brandMaroon }};">
                                    <a href="{{ $actionUrl }}" target="_blank" style="display:inline-block;padding:{{ $compact ? '8px 16px' : '10px 20px' }};font-size:{{ $compact ? '12' : '13' }}px;font-weight:bold;color:#ffffff;text-decoration:none;">
                                        {{ $actionLabel }}
                                    </a>
                                </td>
                            </tr>
                        </table>
                        @endif

                        @if(empty($compact))
                        <p style="margin:14px 0 0;font-size:12px;color:#94a3b8;">Thank you,<br><strong style="color:#64748b;">BMS</strong></p>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="padding:{{ $footerPad }};background:{{ $brandGoldLight }};border-top:2px solid {{ $brandGold }};text-align:center;">
                        <p style="margin:0;font-size:{{ $compact ? '10' : '11' }}px;line-height:1.4;color:#94a3b8;">
                            Automated BMS message · Do not reply
                            @if(!empty($referenceCode)) · {{ $referenceCode }}@endif
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
