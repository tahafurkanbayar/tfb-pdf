<?php
/**
 * Satır içi logo (renkler app.css'teki tema değişkenlerinden; açık/koyu temaya uyar).
 * Dosya halleri: public/assets/img/brand/logo-mark.svg, logo-full.svg, logo-full-inverse.svg.
 *
 * @var bool $word  Kelime işaretini de göster (yalnızca ≥576 px'te; mobilde yalnızca işaret)
 */
$word ??= true;
?>
<svg class="logo-mark" viewBox="0 0 32 32" aria-hidden="true" focusable="false">
    <path class="logo-doc" d="M6.5 1.5H21L29 9.5V27a3.5 3.5 0 0 1-3.5 3.5h-19A3.5 3.5 0 0 1 3 27V5a3.5 3.5 0 0 1 3.5-3.5Z"/>
    <path class="logo-fold" d="M21 1.5V7a2.5 2.5 0 0 0 2.5 2.5H29Z"/>
    <g class="logo-letters" fill="none" stroke-width="4" transform="translate(4.125 15) scale(.5)">
        <path d="M0 2H14M7 2V20"/>
        <path transform="translate(17.5 0)" d="M2 0V20M2 2H12M2 10H10"/>
        <path transform="translate(33 0)" d="M2 0V20M2 2H7.5A4 4 0 0 1 7.5 10H2M2 10H8.5A4 4 0 0 1 8.5 18H2"/>
    </g>
</svg>
<?php if ($word): ?>
<svg class="logo-word d-none d-sm-block" viewBox="0 0 105 20" aria-hidden="true" focusable="false">
    <g class="logo-ink" fill="none" stroke-width="4">
        <path d="M0 2H14M7 2V20"/>
        <path transform="translate(17.5 0)" d="M2 0V20M2 2H12M2 10H10"/>
        <path transform="translate(33 0)" d="M2 0V20M2 2H7.5A4 4 0 0 1 7.5 10H2M2 10H8.5A4 4 0 0 1 8.5 18H2"/>
    </g>
    <g class="logo-accent" fill="none" stroke-width="4">
        <path transform="translate(56 0)" d="M2 0V20M2 2H7.5A4.5 4.5 0 0 1 7.5 11H2"/>
        <path transform="translate(73.5 0)" d="M2 0V20M2 2H6A8 8 0 0 1 6 18H2"/>
        <path transform="translate(93 0)" d="M2 0V20M2 2H12M2 10H10"/>
    </g>
</svg>
<?php endif; ?>
