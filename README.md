# Hingenia · Ruleta de Premios

Plugin de WordPress para la ruleta gamificada de premios del aula de Hingenia.

**Responsabilidades:**

- **Plugin** (este repo) → toda la lógica: premios y sus pesos, AJAX del giro, historial de quién giró y qué ganó, configuración, tracking del clic a WhatsApp y export a CSV.
- **Tema `hingenia-theme`** → solo dibuja la rueda (HTML/CSS/JS) leyendo los premios desde este plugin.

## Instalación

1. Sube el zip a **Plugins → Añadir nuevo → Subir plugin** y actívalo.
2. En el menú lateral aparece **🎁 Ruleta** con tres pantallas:
   - **Premios** — añade, edita, ordena los premios y ajusta el peso de cada uno (más peso = más probabilidad).
   - **Historial** — quién giró, qué ganó, fecha del giro y si reclamó por WhatsApp. Export a CSV.
   - **Configuración** — giros por día, número y mensaje de WhatsApp, textos del banner y del modal.
3. El tema renderiza el banner + modal automáticamente (`hingenia_ruleta_render()` en `page-aula.php`).

## Cómo funciona el peso

Cada premio activo tiene un peso entero (0–999). En cada giro el plugin elige un premio aleatorio **proporcional a su peso**:

| Premio | Peso | Probabilidad |
|---|---|---|
| Curso GRATIS | 1 | 1 / 27 ≈ 3.7% |
| 70% OFF | 2 | 2 / 27 ≈ 7.4% |
| Bono S/ 50 | 3 | 3 / 27 ≈ 11.1% |
| 20% OFF | 10 | 10 / 27 ≈ 37% |

La pantalla **Premios** muestra esa probabilidad en vivo según los pesos que configures.

## Datos guardados por cada giro

- `user_id`, `name`, `email` del usuario que giró
- `prize_index`, `prize_label` del premio ganado
- `token` único (para el botón de reclamo)
- `created_at` (fecha y hora del giro)
- `claimed_at` (si hizo clic en "Reclamar por WhatsApp")

## Reclamo por WhatsApp

El botón de reclamo apunta a `admin-post.php?action=hg_ruleta_claim&t={token}`:

1. Marca el giro como reclamado (`claimed_at`).
2. Redirige al usuario a `https://wa.me/...` con un mensaje pre-armado donde `{nombre}` y `{premio}` se reemplazan.

## Hooks útiles

- `wp_ajax_hg_ruleta_spin` — endpoint del giro (solo logueados).
- `admin_post_hg_ruleta_claim` / `_nopriv` — tracking del clic y redirect a WhatsApp.

## Versión

1.0.0 — primer release público.

---

🤖 Generated with [Claude Code](https://claude.com/claude-code)
