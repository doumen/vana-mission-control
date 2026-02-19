<?php
defined('ABSPATH') || exit;
if (!defined('WP_CLI') || !WP_CLI) return;

final class Vana_CLI_Backfill {
    public static function register(): void {
        WP_CLI::add_command('vana backfill_visits', [__CLASS__, 'run']);
    }

    /**
     * Backfill de _vana_start_date e _vana_tz a partir do JSON.
     *
     * ## OPTIONS
     * [--dry-run]           : Não grava, só imprime.
     * [--only-missing]      : Só processa quem não tem _vana_start_date.
     * [--no-require-hash]   : IGNORA o gatekeeper e processa posts sem hash (perigoso).
     * [--limit=<n>]         : Limite total (0 = sem limite).
     * [--batch=<n>]         : Lote por página (default 200).
     */
    public static function run($args, $assoc_args): void {
        $dry_run = isset($assoc_args['dry-run']);
        $only_missing = isset($assoc_args['only-missing']);
        $limit = isset($assoc_args['limit']) ? max(0, (int)$assoc_args['limit']) : 0;
        $batch = isset($assoc_args['batch']) ? max(20, (int)$assoc_args['batch']) : 200;
        $require_hash = !isset($assoc_args['no-require-hash']); // Default ON (Seguro)

        $processed = 0; $updated = 0; $skipped = 0; $errors = 0; $paged = 1;

        while (true) {
            $meta_query = [];
            if ($only_missing) {
                $meta_query[] = ['key' => '_vana_start_date', 'compare' => 'NOT EXISTS'];
            }
            if ($require_hash) {
                $meta_query[] = ['key' => '_vana_timeline_hash', 'compare' => 'EXISTS'];
                $meta_query[] = ['key' => '_vana_timeline_hash', 'value' => '', 'compare' => '!='];
            }

            $q_args = [
                'post_type' => 'vana_visit', 'post_status' => 'any',
                'posts_per_page' => $batch, 'paged' => $paged,
                'fields' => 'ids', 'no_found_rows' => true,
            ];
            if ($meta_query) $q_args['meta_query'] = $meta_query;

            $q = new WP_Query($q_args);
            $ids = $q->posts;
            if (!$ids) break;

            foreach ($ids as $visit_id) {
                $visit_id = (int)$visit_id;
                if ($limit > 0 && $processed >= $limit) break 2;
                $processed++;

                $timeline_json = (string) get_post_meta($visit_id, '_vana_visit_timeline_json', true);
                if ($timeline_json === '') { $skipped++; continue; }

                try {
                    $derived = Vana_Visit_Materializer::derive_from_timeline_json($timeline_json);
                } catch (Throwable $e) {
                    $errors++; WP_CLI::warning("Erro ID {$visit_id}: {$e->getMessage()}"); continue;
                }

                if ($derived['start_date'] === '' && $derived['tz'] === '') { $skipped++; continue; }
                if ($dry_run) { WP_CLI::log("ID {$visit_id} start_date={$derived['start_date']} tz={$derived['tz']}"); continue; }

                Vana_Visit_Materializer::apply_to_post($visit_id, $derived);
                $updated++;
            }
            $paged++;
        }

        if (!$dry_run) delete_transient('vana_chronological_sequence');
        WP_CLI::success("Processed={$processed} Updated={$updated} Skipped={$skipped} Errors={$errors}" . ($dry_run ? ' (dry-run)' : '') . ($require_hash ? ' (require-hash=on)' : ' (require-hash=off)'));
    }
}
Vana_CLI_Backfill::register();