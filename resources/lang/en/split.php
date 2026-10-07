<?php

return [
    'range_empty' => 'Please enter at least one page or page range.',
    'range_invalid' => '":token" is not a valid page range. Example: 1-3, 5, 8-12',
    'range_zero' => '":token" is invalid: page numbers start at 1.',
    'range_reversed' => '":token" is invalid: a range cannot start after it ends.',
    'range_out_of_bounds' => '":token" is outside the document. The document has :total pages.',
    'mode_label' => 'How should it be split?',
    'modes' => [
        'each' => 'One PDF per page',
        'ranges' => 'One PDF per range',
        'extract' => 'Extract selected pages into one PDF',
    ],
    'mode_help' => [
        'each' => 'Every page of the document becomes a separate file.',
        'ranges' => 'Each range you enter (e.g. 1-3) becomes a separate file.',
        'extract' => 'The selected pages are combined into a single file in the order you enter them.',
    ],
    'ranges_label' => 'Page ranges',
    'ranges_help' => 'Separate with commas or new lines. Example: 1-3, 5, 8-12. "8-" means from page 8 to the end.',
    'select_hint' => 'You can also click the page thumbnails to select pages.',
    'action' => 'Split PDF',
    'outputs_count' => ':count file will be created.|:count files will be created.',
    'download_zip' => 'Download all as ZIP',
    'zip_unavailable' => 'ZIP downloads are not available on this server; you can download the files one by one.',
    'select_page' => 'Page :number',
    'single_page' => 'This document has only one page, so it cannot be split into pages.',
];
