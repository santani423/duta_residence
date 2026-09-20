<table style="width: 100%; border: 0; margin: 0 0 4px;">
    <tr>
        @if ($company['logo'])
            <td style="width: 56px; border: 0; padding: 0;"><img src="{{ $company['logo'] }}" style="width: 48px;" alt=""></td>
        @endif
        <td style="border: 0; padding: 0;">
            <div style="font-size: 15px; font-weight: bold;">{{ $company['name'] }}</div>
            <div style="font-size: 10px; color: #374151;">
                {{ $company['address'] }}
                @if ($company['phone']) &middot; Telp: {{ $company['phone'] }}@endif
                @if ($company['email']) &middot; {{ $company['email'] }}@endif
            </div>
        </td>
    </tr>
</table>
<div style="border-top: 2px solid #000; border-bottom: 1px solid #000; height: 2px; margin-bottom: 12px;"></div>
