| Campo | Nível | Obrigatório | Notas |
| --- | --- | --- | --- |
| visit_id | raiz | ✅ | Usado na URL e nos IDs HTML |
| timezone | raiz | ✅ | Ex: Asia/Kolkata, America/Sao_Paulo |
| days[].date_local | dia | ✅ | Formato YYYY-MM-DD |
| days[].hero.youtube_url | hero | ⭕ | Aceita também facebook_url, instagram_url, drive_url |
| days[].hero.segments[] | hero | ⭕ | Capítulos para seek no player |
| days[].schedule[].status | item | ✅ | done / live / upcoming / break / optional |
| days[].vods[] | vod | ⭕ | Array vazio [] omite a seção |
| days[].photos[].featured | foto | ⭕ | true → column-span: all no grid |
| days[].sangha_moments[].type | momento | ⭕ | quote / moment / service / realization |
| days[].links[] | link | ⭕ | Sobrescreve default do mesmo type |
| photos_submit_url | dia | ⭕ | Fallback: Facebook oficial |
| moments_submit_url | dia | ⭕ | Fallback: Facebook oficial |