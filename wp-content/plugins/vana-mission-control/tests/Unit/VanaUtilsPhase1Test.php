<?php
/**
 * Testes unitários — Vana_Utils Fase 1
 * Cobertura: Tour Identity + Visit Identity
 */

namespace Vana\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Brain\Monkey\Functions;
use Brain\Monkey;

class VanaUtilsPhase1Test extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    // =========================================================================
    // FIXTURES
    // =========================================================================

    /**
     * Tour com todos os metadados presentes.
     */
    private function mock_tour_full(int $id = 10): void {
        Functions\when('get_post_meta')->justReturn('');

        Functions\when('get_post_meta')->alias(function() {
            $args = func_get_args();
            $map = [
                '_vana_region_code' => 'IN',
                '_vana_season_code' => 'KARTIK',
                '_vana_year_start' => '2025',
                '_vana_year_end' => '2026',
                '_vana_title_pt' => 'Índia Kartik 2025-26',
                '_vana_title_en' => 'India Kartik 2025-26',
            ];
            return $map[$args[1] ?? ''] ?? '';
        });
    }

    /**
     * Tour só com título — sem códigos.
     */
    private function mock_tour_title_only(int $id = 20): void {
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('get_the_title')->justReturn('Tour Vraja 2024');
    }

    /**
     * Tour completamente vazia (sem metadados, sem título WP).
     */
    private function mock_tour_empty(int $id = 99): void {
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('get_the_title')->justReturn('');
    }

    /**
     * Visit com todos os metadados presentes.
     */
    private function mock_visit_full(int $id = 100): void {
        Functions\when('get_post_meta')->justReturn('');

        Functions\when('get_post_meta')->alias(function() {
            $args = func_get_args();
            $map = [
                '_vana_location' => 'Navadvīpa',
                '_vana_country_code' => 'in',
                '_vana_start_date' => '2025-11-10',
                '_vana_tour_id' => '10',
            ];
            return $map[$args[1] ?? ''] ?? '';
        });

        Functions\when('get_the_title')
            ->with($id)->justReturn('Navadvipa Visit');
    }

    /**
     * Visit com location como array.
     */
    private function mock_visit_location_array(int $id = 101): void {
        Functions\when('get_post_meta')->justReturn('');

        Functions\when('get_post_meta')->justReturn(['city' => 'Māyāpur', 'state' => 'WB']);

        Functions\when('get_post_meta')->alias(function() {
            $args = func_get_args();
            $map = [
                '_vana_country_code' => 'IN',
            ];
            return $map[$args[1] ?? ''] ?? '';
        });
    }

    /**
     * Visit com location vazia, fallback para timeline JSON.
     */
    private function mock_visit_timeline_json(int $id = 102): void {
        $json = json_encode([[
            'title_pt' => 'Vrindāvana',
            'title_en' => 'Vrindavan',
        ]]);

        Functions\when('get_post_meta')->justReturn('');

        Functions\when('get_post_meta')->alias(function() use ($json) {
            $args = func_get_args();
            $map = [
                '_vana_location' => '',
                '_vana_visit_timeline_json' => $json,
                '_vana_country_code' => 'IN',
            ];
            return $map[$args[1] ?? ''] ?? '';
        });
    }

    /**
     * Visit sem nenhum metadado.
     */
    private function mock_visit_empty(int $id = 199): void {
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('get_the_title')->justReturn('');
    }

    // =========================================================================
    // tour_year_label()
    // =========================================================================

    /** @test */
    public function tour_year_label_mesmo_ano(): void {
        $this->assertSame('2025', \Vana_Utils::tour_year_label(2025, 2025));
    }

    /** @test */
    public function tour_year_label_ano_end_zero(): void {
        $this->assertSame('2025', \Vana_Utils::tour_year_label(2025, 0));
    }

    /** @test */
    public function tour_year_label_anos_diferentes(): void {
        $this->assertSame('25/26', \Vana_Utils::tour_year_label(2025, 2026));
    }

    /** @test */
    public function tour_year_label_ambos_zero(): void {
        $this->assertSame('', \Vana_Utils::tour_year_label(0, 0));
    }

    /** @test */
    public function tour_year_label_so_start(): void {
        $this->assertSame('2024', \Vana_Utils::tour_year_label(2024));
    }

    // =========================================================================
    // resolve_tour_title()
    // =========================================================================

    /** @test */
    public function resolve_tour_title_retorna_meta_lang_pt(): void {
        Functions\when('get_post_meta')->alias(function() {
            $args = func_get_args();
            $map = [
                '_vana_title_pt' => 'Índia Kartik',
            ];
            return $map[$args[1] ?? ''] ?? '';
        });

        $this->assertSame('Índia Kartik', \Vana_Utils::resolve_tour_title(10, 'pt'));
    }

    /** @test */
    public function resolve_tour_title_retorna_meta_lang_en(): void {
        Functions\when('get_post_meta')->alias(function() {
            $args = func_get_args();
            $map = [
                '_vana_title_en' => 'India Kartik',
                '_vana_title_pt' => '',
            ];
            return $map[$args[1] ?? ''] ?? '';
        });

        $this->assertSame('India Kartik', \Vana_Utils::resolve_tour_title(10, 'en'));
    }

    /** @test */
    public function resolve_tour_title_fallback_wp_title(): void {
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('get_the_title')->alias(function() {
            $args = func_get_args();
            $map = [
                '' => 'Tour Kartik',
            ];
            return $map[$args[1] ?? ''] ?? '';
        });

        $this->assertSame('Tour Kartik', \Vana_Utils::resolve_tour_title(10, 'pt'));
    }

    /** @test */
    public function resolve_tour_title_fallback_origin_key(): void {
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('get_the_title')->justReturn('');
        Functions\when('get_post_meta')->alias(function() {
            $args = func_get_args();
            $map = [
                '_vana_origin_key' => 'ind-kartik-2025',
            ];
            return $map[$args[1] ?? ''] ?? '';
        });

        $this->assertSame('ind-kartik-2025', \Vana_Utils::resolve_tour_title(10, 'pt'));
    }

    /** @test */
    public function resolve_tour_title_tour_id_zero_retorna_vazio(): void {
        $this->assertSame('', \Vana_Utils::resolve_tour_title(0));
    }

    // =========================================================================
    // tour_header_label()
    // =========================================================================

    /** @test */
    public function tour_header_label_completo(): void {
        Functions\when('get_post_meta')->alias(function() {
            $args = func_get_args();
            $map = [
                '_vana_region_code' => 'IN',
                '_vana_season_code' => 'KARTIK',
                '_vana_year_start' => '2025',
                '_vana_year_end' => '2026',
            ];
            return $map[$args[1] ?? ''] ?? '';
        });

        $this->assertSame('IN · KARTIK · 25/26', \Vana_Utils::tour_header_label(10));
    }

    /** @test */
    public function tour_header_label_sem_season_fallback_titulo(): void {
        Functions\when('get_post_meta')->alias(function() {
            $args = func_get_args();
            $map = [
                '_vana_region_code' => 'IN',
                '_vana_season_code' => '',
                '_vana_year_start' => '2025',
                '_vana_year_end' => '0',
                '_vana_title_pt' => 'Índia 2025',
            ];
            return $map[$args[1] ?? ''] ?? '';
        });

        $this->assertSame('Índia 2025', \Vana_Utils::tour_header_label(10, 'pt'));
    }

    /** @test */
    public function tour_header_label_tour_id_zero_retorna_vazio(): void {
        $this->assertSame('', \Vana_Utils::tour_header_label(0));
    }

    // =========================================================================
    // tour_full_label()
    // =========================================================================

    /** @test */
    public function tour_full_label_pt_completo(): void {
        Functions\when('get_post_meta')->alias(function() {
            $args = func_get_args();
            $map = [
                '_vana_region_code' => 'IN',
                '_vana_season_code' => 'KARTIK',
                '_vana_year_start' => '2025',
                '_vana_year_end' => '2026',
            ];
            return $map[$args[1] ?? ''] ?? '';
        });

        Functions\when('apply_filters')->returnArg(2);

        $result = \Vana_Utils::tour_full_label(10, 'pt');
        $this->assertSame('Índia · Kartik · 25/26', $result);
    }

    /** @test */
    public function tour_full_label_en_completo(): void {
        Functions\when('get_post_meta')->alias(function() {
            $args = func_get_args();
            $map = [
                '_vana_region_code' => 'IN',
                '_vana_season_code' => 'KARTIK',
                '_vana_year_start' => '2025',
                '_vana_year_end' => '2026',
            ];
            return $map[$args[1] ?? ''] ?? '';
        });

        Functions\when('apply_filters')->returnArg(2);

        $result = \Vana_Utils::tour_full_label(10, 'en');
        $this->assertSame('India · Kartik · 25/26', $result);
    }

    /** @test */
    public function tour_full_label_season_desconhecido_exibe_codigo_bruto(): void {
        Functions\when('get_post_meta')->alias(function() {
            $args = func_get_args();
            $map = [
                '_vana_region_code' => 'IN',
                '_vana_season_code' => 'XYZ_UNKNOWN',
                '_vana_year_start' => '2024',
                '_vana_year_end' => '0',
            ];
            return $map[$args[1] ?? ''] ?? '';
        });

        Functions\when('apply_filters')->returnArg(2);

        // XYZ_UNKNOWN não está no mapa padrão — exibe código bruto
        $result = \Vana_Utils::tour_full_label(10, 'pt');
        $this->assertStringContainsString('XYZ_UNKNOWN', $result);
    }

    // =========================================================================
    // resolve_visit_city()
    // =========================================================================

    /** @test */
    public function resolve_visit_city_meta_string(): void {
        Functions\when('get_post_meta')->alias(function() {
            $args = func_get_args();
            $map = [
                '_vana_location' => 'Navadvīpa',
            ];
            return $map[$args[1] ?? ''] ?? '';
        });

        $this->assertSame('Navadvīpa', \Vana_Utils::resolve_visit_city(100));
    }

    /** @test */
    public function resolve_visit_city_meta_array_com_city(): void {
        Functions\when('get_post_meta')->justReturn(['city' => 'Māyāpur', 'state' => 'WB']);

        $this->assertSame('Māyāpur', \Vana_Utils::resolve_visit_city(101));
    }

    /** @test */
    public function resolve_visit_city_meta_array_com_city_ref(): void {
        Functions\when('get_post_meta')->justReturn(['city_ref' => 'Purī']);

        $this->assertSame('Purī', \Vana_Utils::resolve_visit_city(101));
    }

    /** @test */
    public function resolve_visit_city_fallback_timeline_json_pt(): void {
        $json = json_encode([['title_pt' => 'Vrindāvana', 'title_en' => 'Vrindavan']]);

        Functions\when('get_post_meta')->alias(function() use ($json) {
            $args = func_get_args();
            $map = [
                '_vana_location' => '',
                '_vana_visit_timeline_json' => $json,
            ];
            return $map[$args[1] ?? ''] ?? '';
        });

                Functions\when('get_the_title')->justReturn('');

$this->assertSame('Vrindāvana', \Vana_Utils::resolve_visit_city(102, 'pt'));
    }

    /** @test */
    public function resolve_visit_city_fallback_timeline_json_en(): void {
        $json = json_encode([['title_pt' => 'Vrindāvana', 'title_en' => 'Vrindavan']]);

        Functions\when('get_post_meta')->alias(function() use ($json) {
            $args = func_get_args();
            $map = [
                '_vana_location' => '',
                '_vana_visit_timeline_json' => $json,
            ];
            return $map[$args[1] ?? ''] ?? '';
        });

                Functions\when('get_the_title')->justReturn('');

$this->assertSame('Vrindavan', \Vana_Utils::resolve_visit_city(102, 'en'));
    }

    /** @test */
    public function resolve_visit_city_fallback_wp_title(): void {
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('get_the_title')->alias(function() {
            $args = func_get_args();
            $map = [
                '' => 'Ekacakra',
            ];
            return $map[$args[1] ?? ''] ?? '';
        });

        $this->assertSame('Ekacakra', \Vana_Utils::resolve_visit_city(103));
    }

    /** @test */
    public function resolve_visit_city_visit_id_zero_retorna_vazio(): void {
        $this->assertSame('', \Vana_Utils::resolve_visit_city(0));
    }

    // =========================================================================
    // resolve_visit_country_code()
    // =========================================================================

    /** @test */
    public function resolve_visit_country_code_uppercase(): void {
        Functions\when('get_post_meta')->alias(function() {
            $args = func_get_args();
            $map = [
                '_vana_country_code' => 'in',
            ];
            return $map[$args[1] ?? ''] ?? '';
        });

        $this->assertSame('IN', \Vana_Utils::resolve_visit_country_code(100));
    }

    /** @test */
    public function resolve_visit_country_code_vazio_retorna_string_vazia(): void {
        Functions\when('get_post_meta')->alias(function() {
            $args = func_get_args();
            $map = [
                '_vana_country_code' => '',
            ];
            return $map[$args[1] ?? ''] ?? '';
        });

        $this->assertSame('', \Vana_Utils::resolve_visit_country_code(100));
    }

    /** @test */
    public function resolve_visit_country_code_visit_id_zero(): void {
        $this->assertSame('', \Vana_Utils::resolve_visit_country_code(0));
    }

    // =========================================================================
    // resolve_visit_country_label()
    // =========================================================================

    /** @dataProvider country_label_provider */
    public function test_country_label(
        string $code,
        string $lang,
        string $expected
    ): void {
        Functions\when('apply_filters')->returnArg(2);
        $this->assertSame($expected, \Vana_Utils::resolve_visit_country_label($code, $lang));
    }

    public static function country_label_provider(): array {
        return [
            // [ code,  lang, expected   ]
            ['IN', 'pt', 'Índia'     ],
            ['IN', 'en', 'India'     ],
            ['BR', 'pt', 'Brasil'    ],
            ['BR', 'en', 'Brazil'    ],
            ['US', 'pt', 'EUA'       ],
            ['US', 'en', 'USA'       ],
            ['GB', 'pt', 'Inglaterra'],
            ['GB', 'en', 'England'   ],
            ['XX', 'pt', 'XX'        ], // código desconhecido → retorna bruto
            ['',   'pt', ''          ], // vazio → vazio
        ];
    }

    // =========================================================================
    // visit_nav_label()
    // =========================================================================

    /** @test */
    public function visit_nav_label_sem_country(): void {
        Functions\when('get_post_meta')->alias(function() {
            $args = func_get_args();
            $map = [
                '_vana_location' => 'Navadvīpa',
            ];
            return $map[$args[1] ?? ''] ?? '';
        });

        $this->assertSame('Navadvīpa', \Vana_Utils::visit_nav_label(100, 'pt', false));
    }

    /** @test */
    public function visit_nav_label_com_country(): void {
        Functions\when('get_post_meta')->alias(function() {
            $args = func_get_args();
            $map = [
                '_vana_location' => 'Navadvīpa',
                '_vana_country_code' => 'IN',
            ];
            return $map[$args[1] ?? ''] ?? '';
        });

        $this->assertSame('Navadvīpa [IN]', \Vana_Utils::visit_nav_label(100, 'pt', true));
    }

    /** @test */
    public function visit_nav_label_com_country_mas_code_vazio(): void {
        Functions\when('get_post_meta')->alias(function() {
            $args = func_get_args();
            $map = [
                '_vana_location' => 'Navadvīpa',
                '_vana_country_code' => '',
            ];
            return $map[$args[1] ?? ''] ?? '';
        });

        // Sem colchetes se country_code vazio
        $this->assertSame('Navadvīpa', \Vana_Utils::visit_nav_label(100, 'pt', true));
    }

    // =========================================================================
    // visit_date_label()
    // =========================================================================

    /** @test */
    public function visit_date_label_formato_padrao(): void {
        Functions\when('get_post_meta')->alias(function() {
            $args = func_get_args();
            $map = [
                '_vana_start_date' => '2025-11-10',
            ];
            return $map[$args[1] ?? ''] ?? '';
        });

        $this->assertSame('10/11', \Vana_Utils::visit_date_label(100));
    }

    /** @test */
    public function visit_date_label_formato_customizado(): void {
        Functions\when('get_post_meta')->alias(function() {
            $args = func_get_args();
            $map = [
                '_vana_start_date' => '2025-11-10',
            ];
            return $map[$args[1] ?? ''] ?? '';
        });

        $this->assertSame('10/11/2025', \Vana_Utils::visit_date_label(100, 'd/m/Y'));
    }

    /** @test */
    public function visit_date_label_sem_meta_retorna_vazio(): void {
        Functions\when('get_post_meta')->alias(function() {
            $args = func_get_args();
            $map = [
                '_vana_start_date' => '',
            ];
            return $map[$args[1] ?? ''] ?? '';
        });

        $this->assertSame('', \Vana_Utils::visit_date_label(100));
    }

    /** @test */
    public function visit_date_label_data_invalida_retorna_vazio(): void {
        Functions\when('get_post_meta')->alias(function() {
            $args = func_get_args();
            $map = [
                '_vana_start_date' => 'not-a-date',
            ];
            return $map[$args[1] ?? ''] ?? '';
        });

        $this->assertSame('', \Vana_Utils::visit_date_label(100));
    }

    /** @test */
    public function visit_date_label_visit_id_zero(): void {
        $this->assertSame('', \Vana_Utils::visit_date_label(0));
    }

    // =========================================================================
    // visit_counter_label()
    // =========================================================================

    /** @test */
    public function visit_counter_label_pt_posicao_correta(): void {
        // Tour 10 tem 3 visitas: [100, 101, 102]
        Functions\expect('get_posts')
            ->once()
            ->andReturn([100, 101, 102]);

        $result = \Vana_Utils::visit_counter_label(101, 10, 'pt');
        $this->assertSame('Visita 2 de 3', $result);
    }

    /** @test */
    public function visit_counter_label_en_posicao_correta(): void {
        Functions\expect('get_posts')
            ->once()
            ->andReturn([100, 101, 102]);

        $result = \Vana_Utils::visit_counter_label(100, 10, 'en');
        $this->assertSame('Visit 1 of 3', $result);
    }

    /** @test */
    public function visit_counter_label_ultima_visita(): void {
        Functions\expect('get_posts')
            ->once()
            ->andReturn([100, 101, 102]);

        $result = \Vana_Utils::visit_counter_label(102, 10, 'pt');
        $this->assertSame('Visita 3 de 3', $result);
    }

    /** @test */
    public function visit_counter_label_visita_nao_encontrada_retorna_vazio(): void {
        Functions\expect('get_posts')
            ->andReturn([100, 101, 102]);

        // visit_id 999 não está na lista
        $result = \Vana_Utils::visit_counter_label(999, 10, 'pt');
        $this->assertSame('', $result);
    }

    /** @test */
    public function visit_counter_label_tour_id_zero_retorna_vazio(): void {
        $result = \Vana_Utils::visit_counter_label(100, 0, 'pt');
        $this->assertSame('', $result);
    }

    /** @test */
    public function visit_counter_label_lista_vazia_retorna_vazio(): void {
        Functions\expect('get_posts')->andReturn([]);

        $result = \Vana_Utils::visit_counter_label(100, 10, 'pt');
        $this->assertSame('', $result);
    }

    // =========================================================================
    // get_visit_identity() — contrato de estrutura
    // =========================================================================

    /** @test */
    public function get_visit_identity_retorna_estrutura_completa(): void {
        Functions\when('get_post_meta')->alias(function() {
            $args = func_get_args();
            $map = [
                '_vana_location' => 'Navadvīpa',
                '_vana_country_code' => 'IN',
            ];
            return $map[$args[1] ?? ''] ?? '';
        });

        Functions\when('apply_filters')->returnArg(2);
        Functions\when('get_the_title')->justReturn('Navadvipa Visit');

        $result = \Vana_Utils::get_visit_identity(100, 'pt');

        $this->assertArrayHasKey('id',            $result);
        $this->assertArrayHasKey('city',          $result);
        $this->assertArrayHasKey('country_code',  $result);
        $this->assertArrayHasKey('country_label', $result);
        $this->assertArrayHasKey('title',         $result);

        $this->assertSame(100,         $result['id']);
        $this->assertSame('Navadvīpa', $result['city']);
        $this->assertSame('IN',        $result['country_code']);
        $this->assertSame('Índia',     $result['country_label']);
    }

    /** @test */
    public function get_visit_identity_id_zero_retorna_array_vazio(): void {
        $result = \Vana_Utils::get_visit_identity(0);

        $this->assertSame(0,  $result['id']);
        $this->assertSame('', $result['city']);
        $this->assertSame('', $result['country_code']);
        $this->assertSame('', $result['country_label']);
        $this->assertSame('', $result['title']);
    }

    // =========================================================================
    // get_tour_identity() — contrato de estrutura
    // =========================================================================

    /** @test */
    public function get_tour_identity_retorna_estrutura_completa(): void {
        Functions\when('get_post_meta')->alias(function() {
            $args = func_get_args();
            $map = [
                '_vana_region_code' => 'IN',
                '_vana_season_code' => 'KARTIK',
                '_vana_year_start' => '2025',
                '_vana_year_end' => '2026',
                '_vana_title_pt' => 'Índia Kartik',
            ];
            return $map[$args[1] ?? ''] ?? '';
        });

        Functions\when('apply_filters')->returnArg(2);

        $result = \Vana_Utils::get_tour_identity(10, 'pt');

        $this->assertArrayHasKey('id',           $result);
        $this->assertArrayHasKey('title',        $result);
        $this->assertArrayHasKey('region_code',  $result);
        $this->assertArrayHasKey('season_code',  $result);
        $this->assertArrayHasKey('year_start',   $result);
        $this->assertArrayHasKey('year_end',     $result);
        $this->assertArrayHasKey('year_label',   $result);
        $this->assertArrayHasKey('header_label', $result);
        $this->assertArrayHasKey('full_label',   $result);

        $this->assertSame(10,     $result['id']);
        $this->assertSame('IN',   $result['region_code']);
        $this->assertSame('25/26',$result['year_label']);
    }

    /** @test */
    public function get_tour_identity_id_zero_retorna_array_vazio(): void {
        $result = \Vana_Utils::get_tour_identity(0);

        $this->assertSame(0,  $result['id']);
        $this->assertSame('', $result['title']);
        $this->assertSame('', $result['header_label']);
        $this->assertSame('', $result['full_label']);
    }

}
