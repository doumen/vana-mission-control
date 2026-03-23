$Host_Url = "https://beta.vanamadhuryamdaily.com"
$Page_URL = "$Host_Url/visit/vrindavan-2026-02/"

Write-Host "📍 Fetching page to get nonce..." -ForegroundColor Green

# Suppress certificate validation for self-signed certs
Add-Type -TypeDefinition @"
using System.Net;
using System.Security.Cryptography.X509Certificates;
public class TrustAllCertsPolicy : ICertificatePolicy {
    public bool CheckValidationResult(
        ServicePoint srvPoint, X509Certificate certificate,
        WebRequest request, int certificateProblem) {
        return true;
    }
}
"@
[System.Net.ServicePointManager]::CertificatePolicy = New-Object TrustAllCertsPolicy

try {
    $response = Invoke-WebRequest -Uri $Page_URL -UseBasicParsing -ErrorAction Stop
    $html = $response.Content

    # Extract window.vanaDrawer
    $match = [regex]::Match($html, 'window\.vanaDrawer\s*=\s*(\{[^}]+\})')
    if ($match.Success) {
        Write-Host "✅ Found vanaDrawer data:" -ForegroundColor Green
        Write-Host $match.Groups[1].Value
    } else {
        Write-Host "❌ Could not find window.vanaDrawer in page" -ForegroundColor Red
    }

    # Look for nonce pattern
    $nonce_match = [regex]::Match($html, '"nonce"\s*:\s*"([^"]+)"')
    if ($nonce_match.Success) {
        $nonce = $nonce_match.Groups[1].Value
        Write-Host "`n✅ Found nonce: $nonce" -ForegroundColor Green

        # Now test the AJAX endpoint with the real nonce
        Write-Host "`n📋 Testing AJAX endpoint with nonce..." -ForegroundColor Green

        $ajax_url = "$Host_Url/wp-admin/admin-ajax.php"
        $body = @{
            action = "vana_get_tour_visits"
            tour_id = 360
            visit_id = 359
            lang = "pt"
            _wpnonce = $nonce
        }

        $ajax_response = Invoke-WebRequest -Uri $ajax_url -Method POST -Body $body -UseBasicParsing
        Write-Host "AJAX Response Status: $($ajax_response.StatusCode)" -ForegroundColor Yellow
        Write-Host "Response Body:`n$($ajax_response.Content)" -ForegroundColor Cyan
    }
} catch {
    Write-Host "❌ Error: $($_.Exception.Message)" -ForegroundColor Red
}
