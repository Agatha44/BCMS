@php
    $generatedDate = now()->format('d M Y, H:i');
    $history = is_array($history ?? null) ? $history : [];
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payroll Minutes</title>
    <style>
        @page { margin: 12mm; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10pt;
            color: #222;
            margin: 0;
        }

        .document-title {
            background: #922d2f;
            border-bottom: 4px solid #ffc400;
            color: #fff;
            font-size: 13pt;
            font-weight: bold;
            letter-spacing: .3px;
            margin-bottom: 10px;
            padding: 10px 12px;
            text-transform: uppercase;
        }

        .title {
            color: #922d2f;
            font-size: 12pt;
            font-weight: bold;
            margin-bottom: 6px;
        }

        .subtitle {
            color: #444;
            font-size: 10pt;
            margin-bottom: 12px;
        }

        .card {
            border: 1px solid #e6e6e6;
            border-radius: 8px;
            padding: 10px 12px;
            margin-bottom: 10px;
        }

        .card.accent-prepared { border-left: 6px solid #cfcfcf; }
        .card.accent-initiated { border-left: 6px solid #f0b3b3; }
        .card.accent-examined { border-left: 6px solid #7fd8d1; }
        .card.accent-verified { border-left: 6px solid #9bb8ff; }
        .card.accent-approved { border-left: 6px solid #ffc400; }
        .card.accent-posted { border-left: 6px solid #9be07a; }
        .card.accent-processed { border-left: 6px solid #9be07a; }

        .row {
            display: table;
            width: 100%;
        }
        .col {
            display: table-cell;
            vertical-align: top;
        }

        .action {
            font-size: 11pt;
            font-weight: bold;
            margin-bottom: 2px;
        }

        .meta {
            color: #666;
            font-size: 9.5pt;
            line-height: 1.35;
        }

        .badge {
            border: 1px solid #cfcfcf;
            border-radius: 4px;
            display: inline-block;
            font-size: 9pt;
            font-weight: bold;
            padding: 3px 9px;
            text-transform: capitalize;
            white-space: nowrap;
        }

        .badge.prepared { border-color: #cfcfcf; color: #333; }
        .badge.initiated { border-color: #f0b3b3; color: #b21b1b; }
        .badge.examined { border-color: #7fd8d1; color: #0b6b64; }
        .badge.verified { border-color: #9bb8ff; color: #1f4ed1; }
        .badge.approved { border-color: #ffc400; color: #a06400; }
        .badge.posted { border-color: #9be07a; color: #2f7a00; }
        .badge.processed { border-color: #9be07a; color: #2f7a00; }
    </style>
</head>
<body>
<div class="document-title">Nyerere Bridge Payroll Minutes</div>
<div class="subtitle">
    <strong>Payroll No:</strong> {{ $payrollNumber ?? 'N/A' }} &nbsp;|&nbsp;
    <strong>Generated On:</strong> {{ $generatedDate }}
</div>

@forelse($history as $entry)
    @php
        $actionRaw = (string) ($entry['action'] ?? '');
        $action = trim($actionRaw);
        $badgeLabel = $action;
        $displayTitle = $action;

        if (strtolower($action) === 'posted') {
            $displayTitle = 'Processed';
            $badgeLabel = 'Posted';
        }

        $badgeClass = strtolower($badgeLabel);
        $cardAccentClass = 'accent-' . $badgeClass;
        $performedByName = trim((string) ($entry['performed_by_name'] ?? ''));
        $performedByFallback = trim((string) ($entry['performed_by'] ?? ''));
        $who = $performedByName !== '' ? $performedByName : ($performedByFallback !== '' ? $performedByFallback : 'System');

        $line2 = trim((string) ($entry['comment'] ?? ''));
        if ($line2 === '') {
            $line2 = trim((string) ($entry['status'] ?? ''));
        }

        $ts = $entry['created_at'] ?? null;
        try {
            $tsText = $ts ? \Carbon\Carbon::parse($ts)->format('n/j/Y, g:i:s A') : '';
        } catch (\Exception $e) {
            $tsText = (string) $ts;
        }
    @endphp

    <div class="card {{ $cardAccentClass }}">
        <div class="row">
            <div class="col">
                <div class="action">{{ $displayTitle }}</div>
                <div class="meta">{{ $who }}</div>
                @if($line2 !== '')
                    <div class="meta">{{ $line2 }}</div>
                @endif
                @if($tsText !== '')
                    <div class="meta">{{ $tsText }}</div>
                @endif
            </div>
            <div class="col" style="text-align: right;">
                <span class="badge {{ $badgeClass }}">{{ $badgeLabel }}</span>
            </div>
        </div>
    </div>
@empty
    <div class="card">
        <div class="meta">No history entries found.</div>
    </div>
@endforelse
</body>
</html>

