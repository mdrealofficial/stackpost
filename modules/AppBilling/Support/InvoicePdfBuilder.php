<?php

namespace Modules\AppBilling\Support;

use Illuminate\Support\Str;

class InvoicePdfBuilder
{
    /**
     * @param  array<string, mixed>  $invoice
     */
    public function make(array $invoice): string
    {
        $content = $this->buildPage($invoice);
        $length = strlen($content);

        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj",
            "2 0 obj\n<< /Type /Pages /Count 1 /Kids [3 0 R] >>\nendobj",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>\nendobj",
            "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj",
            "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>\nendobj",
            "6 0 obj\n<< /Length {$length} >>\nstream\n{$content}\nendstream\nendobj",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object."\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    /**
     * @param  array<string, mixed>  $invoice
     */
    protected function buildPage(array $invoice): string
    {
        $ops = [];

        $invoiceRef = $this->fitValue((string) ($invoice['invoice_ref'] ?? 'N/A'), 300, 18, true);
        $generatedLine = 'Generated '.$this->truncate((string) ($invoice['generated_at'] ?? 'N/A'), 22);
        $customerName = $this->fitValue((string) ($invoice['customer_name'] ?? 'N/A'), 215, 15, true);
        $customerUsername = '@'.$this->truncate((string) ($invoice['customer_username'] ?? 'user'), 30);
        $customerEmail = $this->truncate((string) ($invoice['customer_email'] ?? 'N/A'), 36);
        $statusLine = 'Status: '.$this->truncate((string) ($invoice['status'] ?? 'N/A'), 20);
        $issuedLine = 'Issued: '.$this->truncate((string) ($invoice['issued_at'] ?? 'N/A'), 24);
        $gatewayLine = 'Gateway: '.$this->truncate((string) ($invoice['gateway'] ?? 'N/A'), 20);
        $plan = $this->fitValue((string) ($invoice['plan'] ?? 'N/A'), 140, 13);
        $transactionId = $this->fitValue((string) ($invoice['transaction_id'] ?? 'N/A'), 175, 13);
        $source = $this->fitValue((string) ($invoice['source'] ?? 'N/A'), 115, 13);
        $lineDescription = $this->fitValue((string) ($invoice['line_description'] ?? 'N/A'), 255, 12);
        $amount = $this->fitValue((string) ($invoice['amount'] ?? 'N/A'), 80, 12, true);
        $paidAmount = $this->fitValue((string) ($invoice['amount'] ?? 'N/A'), 200, 18, true);
        $paymentStatus = $this->fitValue((string) ($invoice['status'] ?? 'N/A'), 200, 18, true);
        $footerLineOne = 'This invoice was generated automatically from the billing history.';
        $footerLineOne = $this->fitValue($footerLineOne, 500, 10);
        $footerLineTwo = 'Keep this PDF for your accounting, payment confirmation, or internal records.';

        $ops[] = $this->fillRect(0, 0, 612, 792, [250, 247, 242]);
        $ops[] = $this->fillRect(0, 652, 612, 140, [40, 52, 75]);
        $ops[] = $this->fillRect(42, 556, 255, 86, [255, 255, 255]);
        $ops[] = $this->fillRect(315, 556, 255, 86, [255, 255, 255]);
        $ops[] = $this->fillRect(42, 438, 528, 96, [255, 255, 255]);
        $ops[] = $this->fillRect(42, 306, 528, 112, [255, 255, 255]);
        $ops[] = $this->fillRect(42, 228, 255, 56, [247, 250, 252]);
        $ops[] = $this->fillRect(315, 228, 255, 56, [247, 250, 252]);

        $ops[] = $this->strokeRect(42, 556, 255, 86, [226, 232, 240], 1);
        $ops[] = $this->strokeRect(315, 556, 255, 86, [226, 232, 240], 1);
        $ops[] = $this->strokeRect(42, 438, 528, 96, [226, 232, 240], 1);
        $ops[] = $this->strokeRect(42, 306, 528, 112, [226, 232, 240], 1);
        $ops[] = $this->strokeRect(42, 228, 255, 56, [226, 232, 240], 1);
        $ops[] = $this->strokeRect(315, 228, 255, 56, [226, 232, 240], 1);

        $ops[] = $this->text('Invoice', 42, 726, 15, true, [221, 229, 239]);
        $ops[] = $this->text($invoiceRef['text'], 42, 694, $invoiceRef['size'], true, [255, 255, 255]);
        $ops[] = $this->textRight($generatedLine, 570, 742, 11, false, [221, 229, 239]);

        $ops[] = $this->text('Customer', 58, 622, 10, true, [100, 116, 139]);
        $ops[] = $this->text($customerName['text'], 58, 598, $customerName['size'], true, [15, 23, 42]);
        $ops[] = $this->text($customerUsername, 58, 578, 10, false, [71, 85, 105]);
        $ops[] = $this->text($customerEmail, 58, 560, 10, false, [71, 85, 105]);

        $ops[] = $this->text('Invoice Summary', 331, 622, 10, true, [100, 116, 139]);
        $ops[] = $this->text($statusLine, 331, 598, 12, true, [15, 23, 42]);
        $ops[] = $this->text($issuedLine, 331, 578, 10, false, [71, 85, 105]);
        $ops[] = $this->text($gatewayLine, 331, 560, 10, false, [71, 85, 105]);

        $ops[] = $this->text('Billing Snapshot', 58, 512, 12, true, [15, 23, 42]);
        $ops[] = $this->text('Plan', 58, 486, 10, true, [100, 116, 139]);
        $ops[] = $this->text($plan['text'], 58, 468, $plan['size'], false, [15, 23, 42]);
        $ops[] = $this->text('Transaction', 225, 486, 10, true, [100, 116, 139]);
        $ops[] = $this->text($transactionId['text'], 225, 468, $transactionId['size'], false, [15, 23, 42]);
        $ops[] = $this->text('Source', 420, 486, 10, true, [100, 116, 139]);
        $ops[] = $this->text($source['text'], 420, 468, $source['size'], false, [15, 23, 42]);

        $ops[] = $this->text('Charge Details', 58, 396, 12, true, [15, 23, 42]);
        $ops[] = $this->line(58, 382, 554, 382, [226, 232, 240], 1);
        $ops[] = $this->text('Description', 58, 364, 10, true, [100, 116, 139]);
        $ops[] = $this->text('Qty', 350, 364, 10, true, [100, 116, 139]);
        $ops[] = $this->text('Unit Price', 408, 364, 10, true, [100, 116, 139]);
        $ops[] = $this->text('Total', 500, 364, 10, true, [100, 116, 139]);
        $ops[] = $this->line(58, 352, 554, 352, [226, 232, 240], 1);
        $ops[] = $this->text($lineDescription['text'], 58, 332, $lineDescription['size'], false, [15, 23, 42]);
        $ops[] = $this->textRight('1', 365, 332, 12, false, [15, 23, 42]);
        $ops[] = $this->textRight($amount['text'], 485, 332, $amount['size'], false, [15, 23, 42]);
        $ops[] = $this->textRight($amount['text'], 560, 332, $amount['size'], true, [15, 23, 42]);

        $ops[] = $this->text('Amount Paid', 58, 260, 10, true, [100, 116, 139]);
        $ops[] = $this->text($paidAmount['text'], 58, 242, $paidAmount['size'], true, [15, 23, 42]);
        $ops[] = $this->text('Payment Status', 331, 260, 10, true, [100, 116, 139]);
        $ops[] = $this->text($paymentStatus['text'], 331, 242, $paymentStatus['size'], true, [15, 23, 42]);

        $ops[] = $this->text($footerLineOne['text'], 42, 182, $footerLineOne['size'], false, [71, 85, 105]);
        $ops[] = $this->text($footerLineTwo, 42, 166, 10, false, [71, 85, 105]);

        return implode("\n", array_filter($ops));
    }

    /**
     * @param  array<int, int>  $rgb
     */
    protected function fillRect(float $x, float $y, float $width, float $height, array $rgb): string
    {
        return sprintf(
            "q %s rg %.2F %.2F %.2F %.2F re f Q",
            $this->color($rgb),
            $x,
            $y,
            $width,
            $height
        );
    }

    /**
     * @param  array<int, int>  $rgb
     */
    protected function strokeRect(float $x, float $y, float $width, float $height, array $rgb, float $lineWidth = 1): string
    {
        return sprintf(
            "q %.2F w %s RG %.2F %.2F %.2F %.2F re S Q",
            $lineWidth,
            $this->color($rgb),
            $x,
            $y,
            $width,
            $height
        );
    }

    /**
     * @param  array<int, int>  $rgb
     */
    protected function line(float $x1, float $y1, float $x2, float $y2, array $rgb, float $lineWidth = 1): string
    {
        return sprintf(
            "q %.2F w %s RG %.2F %.2F m %.2F %.2F l S Q",
            $lineWidth,
            $this->color($rgb),
            $x1,
            $y1,
            $x2,
            $y2
        );
    }

    /**
     * @param  array<int, int>  $rgb
     */
    protected function text(string $text, float $x, float $y, float $size = 12, bool $bold = false, array $rgb = [15, 23, 42]): string
    {
        $font = $bold ? 'F2' : 'F1';

        return sprintf(
            "BT /%s %.2F Tf %s rg 1 0 0 1 %.2F %.2F Tm (%s) Tj ET",
            $font,
            $size,
            $this->color($rgb),
            $x,
            $y,
            $this->escape($text)
        );
    }

    /**
     * @param  array<int, int>  $rgb
     */
    protected function textRight(string $text, float $rightX, float $y, float $size = 12, bool $bold = false, array $rgb = [15, 23, 42]): string
    {
        $x = max(12, $rightX - $this->estimateTextWidth($text, $size, $bold));

        return $this->text($text, $x, $y, $size, $bold, $rgb);
    }

    /**
     * @param  array<int, int>  $rgb
     */
    protected function color(array $rgb): string
    {
        return implode(' ', array_map(
            fn (int $channel): string => number_format(max(0, min(255, $channel)) / 255, 3, '.', ''),
            $rgb
        ));
    }

    protected function escape(string $value): string
    {
        $value = Str::ascii($value);
        $value = str_replace(['\\', '(', ')'], ['\\\\', '\(', '\)'], $value);

        return preg_replace('/[^\x20-\x7E]/', '', $value) ?: '-';
    }

    protected function fitValue(string $text, float $maxWidth, float $startSize, bool $bold = false, float $minSize = 8): array
    {
        $text = $this->sanitizeInline($text);
        $size = $startSize;

        while ($size > $minSize && $this->estimateTextWidth($text, $size, $bold) > $maxWidth) {
            $size -= 0.5;
        }

        if ($this->estimateTextWidth($text, $size, $bold) > $maxWidth) {
            $text = $this->truncateToWidth($text, $maxWidth, $size, $bold);
        }

        return [
            'text' => $text,
            'size' => $size,
        ];
    }

    protected function truncate(string $text, int $limit): string
    {
        $text = $this->sanitizeInline($text);

        if (strlen($text) <= $limit) {
            return $text;
        }

        return rtrim(substr($text, 0, max(1, $limit - 3))).'...';
    }

    protected function truncateToWidth(string $text, float $maxWidth, float $size, bool $bold = false): string
    {
        $text = $this->sanitizeInline($text);

        if ($text === '') {
            return '-';
        }

        while ($text !== '' && $this->estimateTextWidth($text.'...', $size, $bold) > $maxWidth) {
            $text = substr($text, 0, -1);
        }

        return $text === '' ? '-' : rtrim($text).'...';
    }

    protected function sanitizeInline(string $text): string
    {
        $text = Str::ascii($text);
        $text = preg_replace('/\s+/', ' ', $text ?? '');
        $text = trim((string) $text);

        return $text !== '' ? $text : 'N/A';
    }

    protected function estimateTextWidth(string $text, float $size, bool $bold = false): float
    {
        $factor = $bold ? 0.58 : 0.53;

        return strlen($this->sanitizeInline($text)) * $size * $factor;
    }

    protected function brandInitials(string $brandName): string
    {
        $parts = preg_split('/\s+/', trim($brandName)) ?: [];
        $initials = '';

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            $initials .= strtoupper(substr($part, 0, 1));

            if (strlen($initials) >= 2) {
                break;
            }
        }

        if ($initials !== '') {
            return $initials;
        }

        return strtoupper(substr($brandName, 0, 2)) ?: 'SP';
    }
}
