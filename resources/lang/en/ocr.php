<?php

return [
    'intro' => 'Recognizes text on scanned (image-based) PDF pages and makes the file searchable with selectable text.',
    'accuracy' => 'OCR results may not be 100% accurate; errors are more likely on low-resolution, skewed or handwritten pages.',
    'requirements' => 'This feature requires Tesseract OCR and either Ghostscript or pdftoppm to be installed on the server.',
    'languages' => 'Recognition languages: :languages',
    'too_many_pages' => 'OCR can be applied to documents of at most :max pages at a time.',
    'slow_note' => 'OCR is resource-intensive; depending on the number of pages it may take a few minutes.',
    'action' => 'Run OCR',
];
