<?php
/**
 * BONHAGAN TRANSIT — QR Code Generation & Validation Service
 * Encodes only secure tokens/URLs without private PII.
 */

require_once dirname(__DIR__) . '/config/environment.php';

class QRCodeService {
    /**
     * Generate secure QR token
     */
    public static function generateToken(): string {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // UUID version 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // UUID variant
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Build secure payload for QR code
     * Never contains plain passenger personal info or passwords.
     */
    public static function buildPayload(string $qrToken, string $ticketReference): array {
        $baseUrl = env('FRONTEND_URL', 'http://localhost:3000');
        return [
            'ticket_ref' => $ticketReference,
            'qr_token' => $qrToken,
            'validation_url' => rtrim($baseUrl, '/') . "/staff/scan-ticket.html?token=" . urlencode($qrToken)
        ];
    }

    /**
     * Generate simple SVG visual placeholder / QR representation
     */
    public static function renderSvg(string $qrToken): string {
        $hash = md5($qrToken);
        $size = 180;
        $gridSize = 7;
        $cellSize = floor($size / $gridSize);

        $rects = '';
        for ($i = 0; $i < $gridSize; $i++) {
            for ($j = 0; $j < $gridSize; $j++) {
                $idx = ($i * $gridSize + $j) % 32;
                $char = $hash[$idx];
                $isBlack = (hexdec($char) % 2) === 0 || ($i === 0 || $j === 0 || $i === $gridSize - 1 || $j === $gridSize - 1);
                if ($isBlack) {
                    $x = $j * $cellSize;
                    $y = $i * $cellSize;
                    $rects .= "<rect x='{$x}' y='{$y}' width='{$cellSize}' height='{$cellSize}' fill='#1e293b' />";
                }
            }
        }

        return "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 {$size} {$size}' width='{$size}' height='{$size}'><rect width='100%' height='100%' fill='#ffffff'/>{$rects}</svg>";
    }
}

