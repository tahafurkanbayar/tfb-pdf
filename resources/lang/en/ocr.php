<?php

return [
    'intro' => 'Recognizes text on scanned (image-based) PDF pages and makes the file searchable with selectable text.',
    'accuracy' => 'OCR results may not be 100% accurate; errors are more likely on low-resolution, skewed or handwritten pages.',
    'requirements' => 'Requires Tesseract OCR with Ghostscript/pdftoppm, or a configured Evren OCR connection (cURL and GD).',
    'evren_notice' => 'Page images are sent to Evren for text recognition. The original file stays on this server; Evren’s own data retention terms apply.',
    'evren_consent' => 'I agree to send this document’s page images to Evren for OCR.',
    'evren_consent_required' => 'Select the consent checkbox before sending pages to Evren.',
    'approximate_selection' => 'Page appearance is preserved. OCR text is searchable; text positions are approximate, so selection and search highlights may not align with the original words.',
    'images_invalid' => 'OCR page images are missing or invalid. Reopen the document and try again.',
    'images_too_large' => 'Page images exceed the server upload limit. Split the PDF into smaller parts and try again.',
    'preparing' => 'Preparing page for OCR: :current / :total',
    'evren_failed' => 'The Evren OCR request could not be completed. Check connectivity, API access or time limits and try again.',
    'evren_response' => 'OCR text could not be read from the Evren response. The administrator needs to check the response text field configuration.',
    'evren_rate_limit' => 'The Evren usage limit has been reached. Try again later.',
    'evren_terms' => 'The LLM API terms have not been accepted on the Evren account. Try again after the administrator accepts the terms in their Evren account.',
    'languages' => 'Recognition languages: :languages',
    'too_many_pages' => 'OCR can be applied to documents of at most :max pages at a time.',
    'slow_note' => 'OCR is resource-intensive; depending on the number of pages it may take a few minutes.',
    'action' => 'Run OCR',
];
