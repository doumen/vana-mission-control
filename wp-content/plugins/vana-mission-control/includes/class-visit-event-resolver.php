<?php
/**
 * VisitEventResolver — Fase 1 (pura, sem WP)
 * Resolve eventos e hero a partir de timeline 5.1 + overrides
 *
 * @package VanaMissionControl
 */

if (!defined('ABSPATH')) {
    exit;
}

class VisitEventResolver {
    /**
     * Resolve eventos e hero do dia ativo
     *
     * @param array $timeline Timeline completo (schema 5.1 merged)
     * @param ?array $overrides Overrides decodificados (_vana_overrides_json)
     * @param ?string $requested_event_key event_key solicitado via URL
     * @param ?string $requested_day data solicitada via ?v_day= (YYYY-MM-DD)
     * @param string $visit_timezone timezone da visita para calcular "hoje"
     * @return array
     */
    public static function resolve(
        array $timeline,
        ?array $overrides = null,
        ?string $requested_event_key = null,
        ?string $requested_day = null,
        string $visit_timezone = 'UTC'
    ): array {
        $overrides = $overrides ?? [];

        // 1. Identidade canônica
        $visit_ref = $timeline['visit_ref'] ?? '';

        // 2. Resolução do active_day — 4 regras explícitas do handoff v1.2
        $active_day = [];

        // 2.1 Prioridade 1: requested_event_key → dia que contém o evento
        if ($requested_event_key) {
            foreach ($timeline['days'] ?? [] as $day) {
                foreach ($day['events'] ?? [] as $event) {
                    if (($event['event_key'] ?? '') === $requested_event_key) {
                        $active_day = $day;
                        break 2;
                    }
                }
            }
        }

        // 2.2 Prioridade 2: ?v_day= explícito
        if (empty($active_day) && $requested_day) {
            foreach ($timeline['days'] ?? [] as $day) {
                if (($day['date_local'] ?? '') === $requested_day) {
                    $active_day = $day;
                    break;
                }
            }
        }

        // 2.3 Prioridade 3: data corrente no timezone da visita
        if (empty($active_day)) {
            try {
                $tz = new DateTimeZone($visit_timezone);
                $today = (new DateTimeImmutable('now', $tz))->format('Y-m-d');
                foreach ($timeline['days'] ?? [] as $day) {
                    if (($day['date_local'] ?? '') === $today) {
                        $active_day = $day;
                        break;
                    }
                }
            } catch (Exception $e) {
                // timezone inválido → cai no fallback
            }
        }

        // 2.4 Prioridade 4: primeiro dia
        $active_day = $active_day ?: ($timeline['days'][0] ?? []);

        // 3. Eventos do dia ativo
        $active_events = $active_day['events'] ?? [];

        // 4. Evento ativo
        $active_event = null;
        if ($requested_event_key) {
            foreach ($active_events as $event) {
                if (($event['event_key'] ?? '') === $requested_event_key) {
                    $active_event = $event;
                    break;
                }
            }
        }
        $active_event = $active_event ?: self::resolveActiveEvent($active_events);

        // 5. Hero event — prioridade explícita (D10)
        $hero_event = self::findHeroEvent($active_events, $overrides);

        // Garantia: hero_event nunca null nem vazio
        if (empty($hero_event)) {
            $hero_event = [
                'type'      => 'placeholder',
                'event_key' => null,
                'vod'       => null,
            ];
        }

        return [
            'visit_ref'       => $visit_ref,
            'active_day'      => $active_day,
            'active_day_date' => $active_day['date_local'] ?? '',
            'active_events'   => $active_events,
            'active_event'    => $active_event, // nullable quando não há eventos
            'hero_event'      => $hero_event,
        ];
    }

    /**
     * Resolve o evento ativo (prioridade: live → com VOD → qualquer)
     */
    private static function resolveActiveEvent(array $events): ?array {
        // Primeiro live (qualquer, com ou sem VOD)
        foreach ($events as $event) {
            if (($event['status'] ?? '') === 'live') {
                return $event;
            }
        }
        // Primeiro com VOD
        foreach ($events as $event) {
            if (!empty($event['media']['vods'] ?? [])) {
                return $event;
            }
        }
        // Primeiro evento qualquer
        return $events[0] ?? null;
    }

    /**
     * Encontra o hero event pela prioridade D10
     */
    private static function findHeroEvent(array $events, array $overrides): ?array {
        // 5.1 Override hero (maior prioridade)
        if (isset($overrides['hero']['event_key'])) {
            $override_key = $overrides['hero']['event_key'];
            foreach ($events as $event) {
                if (($event['event_key'] ?? '') === $override_key) {
                    return $event;
                }
            }
        }

        // 5.2 Primeiro live + VOD
        foreach ($events as $event) {
            if (($event['status'] ?? '') === 'live' && !empty($event['media']['vods'] ?? [])) {
                return $event;
            }
        }

        // 5.3 Primeiro com VOD qualquer status
        foreach ($events as $event) {
            if (!empty($event['media']['vods'] ?? [])) {
                return $event;
            }
        }

        // 5.4 Fallback: primeiro evento
        return $events[0] ?? null;
    }
}