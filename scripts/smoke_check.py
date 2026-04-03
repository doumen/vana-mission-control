import urllib.request, ssl, sys

urls = [
    'https://vanamadhuryamdaily.com/beta_html/wp-json/vana/v1/stage-fragment',
    'https://vanamadhuryamdaily.com/beta_html/wp-content/plugins/vana-mission-control/assets/js/VanaAgendaController.js'
]
ctx = ssl.create_default_context()
for url in urls:
    try:
        req = urllib.request.Request(url, headers={'User-Agent': 'vana-smoke/1.0'})
        with urllib.request.urlopen(req, timeout=15, context=ctx) as r:
            print('URL:', url)
            print('HTTP', r.getcode())
            body = r.read(1200).decode('utf-8', 'replace')
            print('---SNIPPET---')
            print(body)
    except Exception as e:
        print('URL:', url)
        print('ERROR', repr(e))
        sys.stdout.flush()
        continue
