# pdf2text
[![Build Status](https://travis-ci.org/cpierce/pdf2text.svg?branch=master)](https://travis-ci.org/cpierce/pdf2text)

PDF to Text Library

## Related Information
This code is an improved version of what can be found at:
http://www.webcheatsheet.com/php/reading_clean_text_from_pdf.php

http://www.adobe.com/devnet/acrobat/pdfs/PDF32000_2008.pdf

## Usage

```
<?php
$pdf = 'test.pdf';
$pdf2text = new \Pdf2text\Pdf2text($pdf);
$output = $pdf2text->decode();
```
