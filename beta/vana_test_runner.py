#!/usr/bin/env python3
"""
Integration com VanaTestEngine via Python
Para usar em Telegram Bot, Agente IA ou Backend

Exemplo de uso:
    from vana_test_runner import rodar_testes_visit, REPORT_FORMAT
    
    # Simples
    relatorio = rodar_testes_visit('https://beta.vanamadhuryamdaily.com/visit/vrindavan-2026-02/')
    print(relatorio['summary'])
    
    # Com verificação
    if relatorio['summary']['failed'] > 0:
        for erro in relatorio['details']:
            if erro['status'] == 'FAIL':
                print(f"❌ {erro['description']}: {erro['error']}")
                if erro['screenshot']:
                    enviar_screenshot_telegram(erro['screenshot'])
"""

import subprocess
import json
import sys
from pathlib import Path
from typing import Dict, List, Optional

# Configuração
SCRIPT_DIR = Path(__file__).parent
VANA_TEST_ENGINE = SCRIPT_DIR / 'check-visit.js'
REPORT_FORMAT = 'json'  # ou 'text'

class VanaTestRunner:
    """
    Wrapper Python para executar testes Vana via Node.js
    """
    
    @staticmethod
    def rodar_testes(url: str, output_format: str = 'json') -> Dict:
        """
        Executa testes para uma página de visita
        
        Args:
            url: URL da página a testar
            output_format: 'json' ou 'text'
            
        Returns:
            Dicionário com relatório estruturado
            
        Raises:
            RuntimeError: Se Node.js ou dependências faltam
            json.JSONDecodeError: Se output não for JSON válido
        """
        
        if not VANA_TEST_ENGINE.exists():
            raise RuntimeError(f"check-visit.js não encontrado em {VANA_TEST_ENGINE}")
        
        # Monta comando
        cmd = [
            'node',
            str(VANA_TEST_ENGINE),
            url,
            f'--output={output_format}'
        ]
        
        try:
            # Executa e captura output
            resultado = subprocess.run(
                cmd,
                capture_output=True,
                text=True,
                timeout=120  # 2 minutos max
            )
            
            # Se output_format='json', parseia
            if output_format == 'json':
                try:
                    relatorio = json.loads(resultado.stdout)
                    return relatorio
                except json.JSONDecodeError as e:
                    raise json.JSONDecodeError(
                        f"Falha ao parsear JSON do teste: {e.msg}",
                        resultado.stdout,
                        e.pos
                    )
            else:
                # Retorna texto bruto
                return {'text': resultado.stdout}
                
        except subprocess.TimeoutExpired:
            raise RuntimeError("Teste excedeu timeout de 120s")
        except FileNotFoundError:
            raise RuntimeError("Node.js não encontrado no PATH")
    
    @staticmethod
    def formatar_para_telegram(relatorio: Dict, url: str) -> str:
        """
        Formata relatório para enviar no Telegram
        
        Retorna:
            String pronta para enviar como mensagem Telegram
        """
        
        summary = relatorio.get('summary', {})
        details = relatorio.get('details', [])
        
        # Emoji baseado em resultado
        emoji_geral = '✅' if summary.get('failed', 0) == 0 else '⚠️'
        
        msg_lines = [
            f"{emoji_geral} **Testes de Visita**",
            f"URL: `{url}`",
            "",
            f"📊 Resultado: {summary.get('passed', 0)}/{summary.get('total', 0)} testes passaram"
        ]
        
        # Lista testes que falharam
        falhas = [d for d in details if d['status'] == 'FAIL']
        if falhas:
            msg_lines.append("")
            msg_lines.append("❌ **Falhas Detectadas:**")
            for fail in falhas:
                msg_lines.append(f"  • {fail['description']}")
                msg_lines.append(f"    Erro: {fail['error'][:100]}...")
                if fail['screenshot']:
                    msg_lines.append(f"    📸 [Screenshot]({fail['screenshot']})")
        
        # Skipados
        skipados = [d for d in details if d['status'] == 'SKIP']
        if skipados:
            msg_lines.append("")
            msg_lines.append("⏭️  **Testes Pulados:**")
            for skip in skipados:
                msg_lines.append(f"  • {skip['description']}")
        
        return "\n".join(msg_lines)
    
    @staticmethod
    def verificar_saude_pagina(url: str) -> Dict[str, bool]:
        """
        Executa testes e retorna status simplificado
        
        Retorna:
            {
                'saudavel': bool,
                'ajax_ok': bool,
                'ui_ok': bool,
                'interacao_ok': bool,
                'visual_ok': bool,
                'detalhes': str
            }
        """
        
        try:
            relatorio = VanaTestRunner.rodar_testes(url)
        except Exception as e:
            return {
                'saudavel': False,
                'ajax_ok': False,
                'ui_ok': False,
                'interacao_ok': False,
                'visual_ok': False,
                'detalhes': f'Erro crítico: {str(e)}'
            }
        
        # Mapeia testes para status
        status_map = {
            'AJAX Endpoint Integration': 'ajax_ok',
            'UI Element: Notify Button exists and is visible': 'ui_ok',
            'Interaction: Drawer opens and populates with content': 'interacao_ok',
            'Visual: Notification button color is correct gold (#FFD906)': 'visual_ok'
        }
        
        resultado = {
            'saudavel': relatorio['summary']['failed'] == 0,
            'ajax_ok': False,
            'ui_ok': False,
            'interacao_ok': False,
            'visual_ok': False,
            'detalhes': ''
        }
        
        for detail in relatorio['details']:
            desc = detail['description']
            for test_name, key in status_map.items():
                if test_name in desc:
                    resultado[key] = detail['status'] == 'PASS'
                    if detail['status'] == 'FAIL':
                        resultado['detalhes'] += f"❌ {desc}: {detail['error']}\n"
        
        return resultado


# Exemplos de uso
if __name__ == '__main__':
    
    # Exemplo 1: Teste simples
    print("=" * 60)
    print("EXEMPLO 1: Teste Simples")
    print("=" * 60)
    
    relatorio = VanaTestRunner.rodar_testes(
        'https://beta.vanamadhuryamdaily.com/visit/vrindavan-2026-02/'
    )
    
    print(json.dumps(relatorio, indent=2))
    
    # Exemplo 2: Formato Telegram
    print("\n" + "=" * 60)
    print("EXEMPLO 2: Mensagem Telegram")
    print("=" * 60)
    
    msg_telegram = VanaTestRunner.formatar_para_telegram(
        relatorio,
        'https://beta.vanamadhuryamdaily.com/visit/vrindavan-2026-02/'
    )
    print(msg_telegram)
    
    # Exemplo 3: Verificação de Saúde
    print("\n" + "=" * 60)
    print("EXEMPLO 3: Status de Saúde")
    print("=" * 60)
    
    saude = VanaTestRunner.verificar_saude_pagina(
        'https://beta.vanamadhuryamdaily.com/visit/vrindavan-2026-02/'
    )
    print(json.dumps(saude, indent=2))
