<?php

// PDF change warnings (spec §36)
return [
    'signature_invalidated' => 'This document contains a digital signature. Any operation that changes the document invalidates existing digital signatures; they cannot be verified in the new file.',
    'forms_removed' => 'Form fields are not carried over as fillable fields; only their appearance is kept.',
    'embedded_files_removed' => 'Files embedded in the document are not carried over to the new file.',
    'bookmarks_removed' => 'Bookmarks (table of contents) are not carried over to the new file.',
    'javascript_removed' => 'JavaScript actions in the document are not carried over to the new file.',
    'tags_removed' => 'Accessibility tags (tagged PDF structure) are not carried over to the new file.',
    'metadata_changed' => 'Document properties (title, author and other metadata) may change in the new file.',
    'links_removed' => 'The appearance of the pages is preserved, but clickable links and comments (annotations) are not carried over.',
    'encryption_changed' => 'Encryption and permission settings of the source file are not carried over to the new file.',
    'font_substitution' => 'Fonts that are not available on the server may be replaced with similar ones; text may look different.',
    'layout_changes' => 'Page layout, line breaks and page breaks may differ from the original document.',
    'office_forms' => 'Form controls in the Office document may not remain fillable fields in the PDF.',
    'embedded_objects' => 'Embedded objects (charts, OLE objects, videos) may be converted to static images or omitted.',
    'rasterized_pages' => 'Redacted pages are converted to images: text on these pages can no longer be selected or searched.',
    'ocr_accuracy' => 'OCR results may not be 100% accurate. Compare important information with the original document.',
    'images_recompressed' => 'Images were saved again at a lower resolution and quality; this may be noticeable when zooming in or printing.',
    'office_signatures' => 'Digital signatures in the Office document are not carried over; the converted file contains no signature.',
];
