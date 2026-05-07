@props(['type' => 'veg', 'size' => 14])
@php
    // FSSAI-style food-type indicator: bordered square + colored mark inside.
    $cfg = match($type) {
        'veg'      => ['border' => '#0e7c2f', 'mark' => 'dot',  'color' => '#0e7c2f', 'title' => 'Veg'],
        'non_veg'  => ['border' => '#b91c1c', 'mark' => 'tri',  'color' => '#b91c1c', 'title' => 'Non-veg'],
        'egg'      => ['border' => '#b8860b', 'mark' => 'dot',  'color' => '#b8860b', 'title' => 'Contains egg'],
        'jain'     => ['border' => '#0e7c2f', 'mark' => 'tri-up','color'=> '#0e7c2f', 'title' => 'Jain'],
        'beverage' => ['border' => '#0e7c2f', 'mark' => 'dot',  'color' => '#0e7c2f', 'title' => 'Beverage'],
        'liquor'   => ['border' => '#b91c1c', 'mark' => 'glass','color' => '#b91c1c', 'title' => 'Alcoholic'],
        default    => ['border' => '#475569', 'mark' => 'dot',  'color' => '#475569', 'title' => ucfirst($type)],
    };
@endphp
<span title="{{ $cfg['title'] }}"
      class="inline-flex items-center justify-center"
      style="width: {{ $size }}px; height: {{ $size }}px; border: 1.5px solid {{ $cfg['border'] }}; border-radius: 2px; background:#fff; flex-shrink:0;">
    @if($cfg['mark'] === 'dot')
        <span style="width: {{ max(4, $size/2.4) }}px; height: {{ max(4, $size/2.4) }}px; border-radius: 50%; background: {{ $cfg['color'] }};"></span>
    @elseif($cfg['mark'] === 'tri')
        <span style="width:0;height:0;border-left:{{ max(3, $size/3.5) }}px solid transparent;border-right:{{ max(3, $size/3.5) }}px solid transparent;border-top:{{ max(5, $size/2.2) }}px solid {{ $cfg['color'] }};"></span>
    @elseif($cfg['mark'] === 'tri-up')
        <span style="width:0;height:0;border-left:{{ max(3, $size/3.5) }}px solid transparent;border-right:{{ max(3, $size/3.5) }}px solid transparent;border-bottom:{{ max(5, $size/2.2) }}px solid {{ $cfg['color'] }};"></span>
    @elseif($cfg['mark'] === 'glass')
        <span style="font-size: {{ max(8, $size - 4) }}px; line-height: 1; color: {{ $cfg['color'] }}; font-weight: 700;">L</span>
    @endif
</span>
