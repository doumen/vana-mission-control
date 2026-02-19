<?php
/**
 * Validador de Contract v3.0
 */

class Vana_Contract {

    public const SUPPORTED_VERSIONS = ['3.0'];
    public const CONTRACT_ID = 'vana.mission';

    public static function validate(array $payload): array {
        $errors = [];

        if (!isset($payload['contract_id']) || $payload['contract_id'] !== self::CONTRACT_ID) {
            $errors[] = 'contract_id inválido ou ausente';
        }

        if (!isset($payload['contract_version']) || !in_array($payload['contract_version'], self::SUPPORTED_VERSIONS, true)) {
            $errors[] = sprintf('contract_version não suportada. Use: %s', implode(', ', self::SUPPORTED_VERSIONS));
        }

        if (!isset($payload['tour']) || !is_array($payload['tour'])) {
            $errors[] = 'Seção "tour" é obrigatória';
            return $errors;
        }

        $tour = $payload['tour'];

        if (empty($tour['origin_key'])) {
            $errors[] = 'tour.origin_key é obrigatório';
        } elseif (strpos((string) $tour['origin_key'], 'tour:') !== 0) {
            // minimal guardrail para evitar colisões futuras
            $errors[] = 'tour.origin_key deve começar com "tour:"';
        }

        if (empty($tour['title'])) {
            $errors[] = 'tour.title é obrigatório';
        }

        if (empty($tour['slug'])) {
            $errors[] = 'tour.slug é obrigatório';
        }

        $valid_themes = ['latam', 'india', 'russia', 'europe'];
        if (empty($tour['theme']) || !in_array($tour['theme'], $valid_themes, true)) {
            $errors[] = sprintf('tour.theme inválido. Use: %s', implode(', ', $valid_themes));
        }

        return $errors;
    }
}
