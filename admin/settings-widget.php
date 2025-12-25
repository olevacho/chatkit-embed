<?php
/**
 * ChatKit widget settings page
 * Allows pasting ChatKit Playground config as JS-like object; converts to JSON.
 */

/**
 * Convert a JS-style object literal (Playground export) to valid JSON.
 * Handles:
 *   - Unquoted keys: theme: { ... }         → "theme": { ... }
 *   - Single quotes: 'light'               → "light"
 *   - Line/block comments: // ...,
 *   - Trailing commas in objects/arrays
 *
 * NOTE: This is intentionally conservative for Playground-like snippets:
 * no functions, no regex literals, no template strings.
 */
if (!function_exists('s2b_chatkit_js_object_to_json')) {
    function s2b_chatkit_js_object_to_json(string $js): string {
        // 1. Remove JS comments
        // Line comments
        $js = preg_replace('#//.*#', '', $js);
        // Block comments
        $js = preg_replace('#/\*.*?\*/#s', '', $js);

        // 2. Replace single-quoted strings with double-quoted strings
        // This is a simple heuristic, good enough for Playground snippets.
        $js = preg_replace_callback(
            "/'([^'\\\\]*(?:\\\\.[^'\\\\]*)*)'/",
            function ($m) {
                $str = $m[1];
                // Escape existing double quotes inside the string
                $str = str_replace('"', '\"', $str);
                return '"' . $str . '"';
            },
            $js
        );

        // 3. Quote unquoted keys: theme: { ... } → "theme": { ... }
        // We try to avoid touching already-quoted keys.
        $js = preg_replace('/([a-zA-Z0-9_]+)\s*:/', '"$1":', $js);

        // 4. Remove trailing commas in objects/arrays: { "a": 1, } → { "a": 1 }
        $js = preg_replace('/,\s*([}\]])/', '$1', $js);

        // 5. Trim whitespace
        $js = trim($js);

        return $js;
    }
}

// Admin page registration
add_action('admin_menu', function () {
    add_options_page(
        'ChatKit Widget',
        'ChatKit Widget',
        'manage_options',
        'chatkit-widget',
        function () {
            ?>
            <div class="wrap">
              <h1>ChatKit Widget</h1>
              <form method="post" action="options.php">
                <?php
                settings_fields('chatkit_widget');
                do_settings_sections('chatkit_widget');
                submit_button();
                ?>
              </form>
            </div>
            <?php
        }
    );
});

add_action('admin_init', function () {

    // 🔹 Default options (Playground config)
    register_setting(
        'chatkit_widget',
        'chatkit_widget_default_options',
        [
            'type'              => 'string',
            'sanitize_callback' => function ($value) {

    $value = trim((string)$value);

    if ($value === '') {
        return '';
    }

    // 1) FIRST TRY: is it already valid JSON?
    $decoded = json_decode($value, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        // Strip unwanted "api" key
        if (isset($decoded['api'])) {
            unset($decoded['api']);
        }

        return wp_json_encode(
            $decoded,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    // 2) OTHERWISE: treat it as JS-like object → convert
    $json = s2b_chatkit_js_object_to_json($value);
    //var_dump($json);
    //die();
    $decoded = json_decode($json, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        add_settings_error(
            'chatkit_widget_default_options',
            'chatkit_widget_default_options_json_error',
            'Invalid ChatKit options (neither JSON nor JS-like object). Last change was not saved.',
            'error'
        );
        return get_option('chatkit_widget_default_options', '');
    }

    // Strip unwanted api
    if (isset($decoded['api'])) {
        unset($decoded['api']);
    }

    return wp_json_encode(
        $decoded,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
}
,
        ]
    );

    //  Extra CSS wrapper
    register_setting(
	    'chatkit_widget',
	    'chatkit_widget_custom_css',
	    [
		'type'              => 'string',
		'sanitize_callback' => 'mychatkit_sanitize_custom_css',
	    ]
	);


    add_settings_section('chatkit_widget_main', 'Defaults', '__return_false', 'chatkit_widget');

    add_settings_field('chatkit_widget_default_options', 'Default Options (Playground config)', function () {
        $v = get_option('chatkit_widget_default_options', '');

        if (!$v) {
            // sensible initial JSON defaults
            $v = wp_json_encode(
                [
                    "theme" => [
                        "colorScheme" => "light",
                        "radius"      => "round",
                        "density"     => "normal",
                    ],
                    "composer" => [
                        "placeholder" => "Ask me anything…",
                    ],
                    "startScreen" => [
                        "greeting" => "How can I help?",
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
        }

echo '<details class="chatkit-shortcode-help" style="margin-top:20px;">
  <summary style="font-weight:600;cursor:pointer;">Shortcode usage & parameters</summary>
  <div style="margin-top:10px;">

    <p><code>[chatkit_widget]</code> renders your ChatKit chat UI.  
    Visual appearance (theme, fonts, prompts, tools, widgets) is controlled by the JSON above.  
    The shortcode attributes control <strong>where and how</strong> the chat appears on the page.</p>

    <h3>Basic examples</h3>
    <pre style="background:#f5f5f5;padding:8px;border-radius:4px;white-space:pre-wrap;">
[chatkit_widget
    workflow="wf_123..."
    mode="inline"]
    </pre>

    <pre style="background:#f5f5f5;padding:8px;border-radius:4px;white-space:pre-wrap;">
[chatkit_widget
    workflow="wf_123..."
    mode="bubble"
    position="br"
    label="Need help?"
    icon="https://example.com/chat-icon.svg"
    panel_width="420px"
    panel_height="620px"]
    </pre>

    <pre style="background:#f5f5f5;padding:8px;border-radius:4px;white-space:pre-wrap;">
[chatkit_widget
    workflow="wf_123..."
    mode="modal"
    label="Chat with us"
    icon="https://example.com/chat-icon.svg"]
    </pre>

    <h3>Common attributes (all modes)</h3>
    <table class="widefat striped" style="max-width:900px;">
      <thead>
        <tr>
          <th>Attribute</th>
          <th>Type</th>
          <th>Default</th>
          <th>Description</th>
        </tr>
      </thead>
      <tbody>

        <!-- MODE -->
        <tr>
          <td><code>mode</code></td>
          <td><code>inline</code>, <code>bubble</code>, <code>modal</code></td>
          <td>Select widget type: embedded block, floating bubble, or modal dialog.</td>
        </tr>

        <!-- WORKFLOW -->
        <tr>
          <td><code>workflow</code></td>
          <td>string (wf_xxx...)</td>
          <td>AgentBuilder workflow ID. Required for connecting to OpenAI.</td>
        </tr>


        <!-- LABEL -->
        <tr>
          <td><code>label</code></td>
          <td>string</td>
          <td>Text shown on the launcher button (“Chat”, “Help?”...).</td>
        </tr>

        <!-- ICON -->
        <tr>
          <td><code>icon</code></td>
          <td>URL to image</td>
          <td>Optional icon displayed before the label on the launcher.</td>
        </tr>

        <!-- PANEL WIDTH -->
        <tr>
          <td><code>panel_width</code></td>
          <td>CSS size</td>
          <td>Width of the floating bubble panel.</td>
        </tr>

        <!-- PANEL HEIGHT -->
        <tr>
          <td><code>panel_height</code></td>
          <td>CSS size</td>
          <td>Height of the floating bubble panel.</td>
        </tr>

        <!-- JSON OPTIONS -->
        <tr>
          <td><code>options</code></td>
          <td>JSON</td>
          <td>Raw ChatKit config JSON (theme, startScreen, composer, etc.).</td>
        </tr>

        <!-- ===== NEW ATTRIBUTES  ===== -->

        <tr>
          <td><code>launcher_bg</code></td>
          <td>CSS color string</td>
          <td>Override launcher background color. Example: <code>#1a73e8</code>.</td>
        </tr>

        <tr>
          <td><code>launcher_color</code></td>
          <td>CSS color string</td>
          <td>Text/icon color for the launcher button.</td>
        </tr>

        <tr>
          <td><code>launcher_shadow</code></td>
          <td><code>0</code> or <code>1</code></td>
          <td>Disable shadow when set to <code>0</code>. Default: shadow enabled.</td>
        </tr>

        <tr>
          <td><code>start_open</code></td>
          <td><code>1</code> / <code>0</code></td>
          <td>Automatically open the bubble or modal on page load.</td>
        </tr>

        <tr>
          <td><code>hide_on_mobile</code></td>
          <td><code>1</code> / <code>0</code></td>
          <td>Hide the entire widget when screen width &lt; 768px.</td>
        </tr>

        <tr>
          <td><code>z_index</code></td>
          <td>integer</td>
          <td>Custom z-index for launcher + panel. Useful when overlapping plugins.</td>
        </tr>

      </tbody>
    </table>

    <h3>Bubble mode (<code>mode="bubble"</code>)</h3>
    <table class="widefat striped" style="max-width:900px;margin-top:10px;">
      <thead>
        <tr>
          <th>Attribute</th>
          <th>Type</th>
          <th>Default</th>
          <th>Effect</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><code>position</code></td>
          <td><code>tl</code> | <code>tr</code> | <code>bl</code> | <code>br</code></td>
          <td><code>br</code></td>
          <td>Corner of the screen for the launcher and floating panel: top/bottom + left/right.</td>
        </tr>
        <tr>
          <td><code>panel_width</code></td>
          <td>CSS size</td>
          <td><code>380px</code></td>
          <td>Width of the floating chat panel (e.g. <code>420px</code>, <code>30rem</code>).</td>
        </tr>
        <tr>
          <td><code>panel_height</code></td>
          <td>CSS size</td>
          <td><code>560px</code></td>
          <td>Height of the floating chat panel.</td>
        </tr>
      </tbody>
    </table>

    <h3>Modal mode (<code>mode="modal"</code>)</h3>
    <p>Modal mode uses a full-screen overlay and a centered dialog. The launcher is always bottom-right.</p>
    <table class="widefat striped" style="max-width:900px;margin-top:10px;">
      <thead>
        <tr>
          <th>Attribute</th>
          <th>Type</th>
          <th>Default</th>
          <th>Effect</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><code>label</code></td>
          <td>string</td>
          <td><code>Chat</code></td>
          <td>Text on the bottom-right launcher button.</td>
        </tr>
        <tr>
          <td><code>icon</code></td>
          <td>URL</td>
          <td><code>(empty)</code></td>
          <td>Optional icon on the launcher.</td>
        </tr>
        <tr>
          <td><code>panel_width</code>, <code>panel_height</code></td>
          <td>—</td>
          <td>—</td>
          <td>Currently ignored in <code>modal</code> mode; size is controlled by CSS (<code>.ckw-modal</code>).</td>
        </tr>
      </tbody>
    </table>

    <h3>Inline mode (<code>mode="inline"</code>)</h3>
    <p>
      Inline mode renders the chat directly inside the content where the shortcode appears.<br>
      Panel width/height attributes are ignored; you can control height via the JSON (<code>style.minHeight</code>)
      or your own CSS targeting <code>openai-chatkit</code>.
    </p>

    <p><strong>Note:</strong> All visual styling <em>inside</em> the chat (colors, typography, prompts, tools, widgets)
    is controlled by the ChatKit options JSON above, not by shortcode attributes.</p>
  </div>
</details>

';



?><div class="ckw-shortcode-generator" style="margin-top:20px;padding:12px;border:1px solid #ccd0d4;border-radius:4px;background:#f8f9fa;">
          <h3 style="margin-top:0;">Shortcode generator</h3>
          <p>Fill in the fields below; the shortcode will update automatically. Copy it and paste wherever you need the widget.</p>

          <!-- Common fields -->
          <div class="ckw-sc-group ckw-sc-group-common">
            <p>
              <label for="ckw-sc-workflow"><strong>Workflow ID</strong> (required)</label><br>
              <input type="text" id="ckw-sc-workflow" class="regular-text" placeholder="wf_123..." />
            </p>

            <p>
              <label for="ckw-sc-mode"><strong>Mode</strong></label><br>
              <select id="ckw-sc-mode">
                <option value="inline">inline</option>
                <option value="bubble" selected>bubble</option>
                <option value="modal">modal</option>
              </select>
            </p>

            <p class="ckw-sc-label-icon">
              <label for="ckw-sc-label"><strong>Label</strong> (launcher text)</label><br>
              <input type="text" id="ckw-sc-label" class="regular-text" placeholder="Chat, Help?..." />
            </p>

            <p class="ckw-sc-label-icon">
              <label for="ckw-sc-icon"><strong>Icon</strong> (optional)</label><br>
              <input type="text" id="ckw-sc-icon" class="regular-text" placeholder="🤖 or &lt;svg&gt;..."/>
            </p>

            <p>
              <label for="ckw-sc-launcher-bg"><strong>Launcher background</strong> (CSS color)</label><br>
              <input type="color" id="ckw-sc-launcher-bg"  name="ckw-sc-launcher-bg"   value="#1a73e8" />
            </p>

            <p>
              <label for="ckw-sc-launcher-color"><strong>Launcher text/icon color</strong></label><br>
              <input type="color" id="ckw-sc-launcher-color"  name="ckw-sc-launcher-color"  value="#ffffff" />
            </p>

            <p>
              <label>
                <input type="checkbox" id="ckw-sc-launcher-shadow-disable" />
                Disable launcher shadow (<code>launcher_shadow="0"</code>)
              </label>
            </p>

            <p>
              <label>
                <input type="checkbox" id="ckw-sc-start-open" />
                Automatically open on page load (<code>start_open="1"</code>)
              </label>
            </p>

            <p>
              <label>
                <input type="checkbox" id="ckw-sc-hide-mobile" />
                Hide on screens &lt; 768px (<code>hide_on_mobile="1"</code>)
              </label>
            </p>

            <p>
              <label for="ckw-sc-zindex"><strong>z_index</strong> (integer)</label><br>
              <input type="number" id="ckw-sc-zindex" class="small-text" placeholder="e.g. 9999" />
            </p>
          </div>

          <!-- Bubble-only fields (mode = bubble) -->
          <div class="ckw-sc-group ckw-sc-group-bubble" style="margin-top:10px;">
            <h4 style="margin-bottom:6px;">Bubble mode options</h4>

            <p>
              <label for="ckw-sc-position"><strong>Position</strong></label><br>
              <select id="ckw-sc-position">
                <option value="br" selected>br (bottom-right)</option>
                <option value="bl">bl (bottom-left)</option>
                <option value="tr">tr (top-right)</option>
                <option value="tl">tl (top-left)</option>
              </select>
            </p>

            <p>
              <label for="ckw-sc-panel-width"><strong>Panel width</strong> (px)</label><br>
              <input type="number" id="ckw-sc-panel-width" class="small-text" placeholder="e.g. 500" />
              <span class="description">px will be added automatically.</span>
            </p>

            <p>
              <label for="ckw-sc-panel-height"><strong>Panel height</strong> (px)</label><br>
              <input type="number" id="ckw-sc-panel-height" class="small-text" placeholder="e.g. 620" />
              <span class="description">px will be added automatically.</span>
            </p>
          </div>

          <!-- Modal-only note (mode = modal) -->
          <div class="ckw-sc-group ckw-sc-group-modal" style="margin-top:10px;display:none;">
            <h4 style="margin-bottom:6px;">Modal mode note</h4>
            <p class="description">
              In <code>modal</code> mode, <code>panel_width</code> and <code>panel_height</code> shortcode attributes
              are currently ignored; the size is controlled by CSS on <code>.ckw-modal</code>.
            </p>
          </div>

          <hr style="margin:12px 0;">

          <p>
            <label for="ckw-sc-output"><strong>Generated shortcode</strong></label><br>
            <textarea id="ckw-sc-output" class="large-text code" rows="4" readonly></textarea>
          </p>
          <p>
            <button type="button" class="button" id="ckw-sc-copy">Copy to clipboard</button>
            <span id="ckw-sc-copy-status" style="margin-left:8px;"></span>
          </p>
        </div>

        <script>
        (function() {
          var wf          = document.getElementById('ckw-sc-workflow');
          if (!wf) return; // safety

          var mode        = document.getElementById('ckw-sc-mode');
          var labelEl     = document.getElementById('ckw-sc-label');
          var iconEl      = document.getElementById('ckw-sc-icon');
          var launchBg    = document.getElementById('ckw-sc-launcher-bg');
          var launchColor = document.getElementById('ckw-sc-launcher-color');
          var disableShadow = document.getElementById('ckw-sc-launcher-shadow-disable');
          var startOpen   = document.getElementById('ckw-sc-start-open');
          var hideMobile  = document.getElementById('ckw-sc-hide-mobile');
          var zIndex      = document.getElementById('ckw-sc-zindex');

          var posEl       = document.getElementById('ckw-sc-position');
          var panelW      = document.getElementById('ckw-sc-panel-width');
          var panelH      = document.getElementById('ckw-sc-panel-height');

          var bubbleGroup = document.querySelector('.ckw-sc-group-bubble');
          var modalGroup  = document.querySelector('.ckw-sc-group-modal');

          var out         = document.getElementById('ckw-sc-output');
          var copyBtn     = document.getElementById('ckw-sc-copy');
          var copyStatus  = document.getElementById('ckw-sc-copy-status');

          function escAttr(val) {
            return String(val).replace(/"/g, '&quot;');
          }

          function updateModeVisibility() {
            var m = mode.value || 'inline';
            if (bubbleGroup) bubbleGroup.style.display = (m === 'bubble') ? '' : 'none';
            if (modalGroup)  modalGroup.style.display  = (m === 'modal')  ? '' : 'none';
          }

          function rebuildShortcode() {
            var m = mode.value || 'inline';
            var attrs = [];

            function add(name, value) {
              if (value === undefined || value === null || value === '') return;
              attrs.push(name + '="' + escAttr(value) + '"');
            }

            var wfVal = wf.value.trim();
            if (wfVal) {
              add('workflow', wfVal);
            }

            // Always include mode explicitly
            add('mode', m);

            // Common fields
            var labelVal = labelEl.value.trim();
            if (labelVal) add('label', labelVal);

            var iconVal = iconEl.value.trim();
            if (iconVal) add('icon', iconVal);

            var bgVal = launchBg.value.trim();
            if (bgVal) add('launcher_bg', bgVal);

            var colorVal = launchColor.value.trim();
            if (colorVal) add('launcher_color', colorVal);

            // launcher_shadow: checkbox acts as "disable shadow"
            if (disableShadow.checked) {
              add('launcher_shadow', '0'); // 0 = disable shadow
            }

            if (startOpen.checked) {
              add('start_open', '1');
            }

            if (hideMobile.checked) {
              add('hide_on_mobile', '1');
            }

            var zVal = zIndex.value.trim();
            if (zVal && zVal !== '0') {
              add('z_index', zVal);
            }

            // Bubble-specific attributes
            if (m === 'bubble') {
              var posVal = posEl.value || '';
              if (posVal) add('position', posVal);

              var wNum = parseInt(panelW.value, 10);
              if (!isNaN(wNum) && wNum > 0) {
                add('panel_width', wNum + 'px');
              }

              var hNum = parseInt(panelH.value, 10);
              if (!isNaN(hNum) && hNum > 0) {
                add('panel_height', hNum + 'px');
              }
            }

            var shortcode;
		if (attrs.length === 0) {
		  shortcode = '[chatkit_widget]';
		} else {
		  shortcode = '[chatkit_widget ' + attrs.join(' ') + ']';
		}


            out.value = shortcode;
          }

          function bind(el) {
            if (!el) return;
            el.addEventListener('input', rebuildShortcode);
            el.addEventListener('change', function() {
              if (el === mode) updateModeVisibility();
              rebuildShortcode();
            });
          }

          [
            wf, mode, labelEl, iconEl, launchBg, launchColor,
            disableShadow, startOpen, hideMobile, zIndex,
            posEl, panelW, panelH
          ].forEach(bind);

          if (copyBtn) {
            copyBtn.addEventListener('click', function() {
              if (!out.value) return;
              out.focus();
              out.select();
              var ok = false;

              try {
                ok = document.execCommand('copy');
              } catch (e) {}

              if (!ok && navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(out.value).then(function() {
                  ok = true;
                  showCopyStatus(true);
                }).catch(function() {
                  showCopyStatus(false);
                });
              } else {
                showCopyStatus(ok);
              }
            });
          }

          function showCopyStatus(success) {
            if (!copyStatus) return;
            copyStatus.textContent = success ? 'Copied!' : 'Press Ctrl+C / Cmd+C to copy.';
            setTimeout(function() {
              copyStatus.textContent = '';
            }, 2000);
          }

          // Init
          updateModeVisibility();
          rebuildShortcode();
        })();
        </script>
        <?php




        echo '<textarea name="chatkit_widget_default_options" id="chatkit_widget_default_options" rows="18" style="width:100%;font-family:monospace;">'
             . esc_textarea($v)
             . '</textarea>';

        echo '<p class="description">'
            . 'You can paste a ChatKit options object from the Playground here. '
            . 'JS-like syntax is accepted (unquoted keys, single quotes, comments, trailing commas) '
            . 'and will be converted to JSON automatically. '
            . 'We ignore the <code>api</code> key because the plugin configures it.'
            . '</p>';
            
            

$stored_presets = get_option('chatkit_widget_presets', []);
if (!is_array($stored_presets)) {
    $stored_presets = [];
}
$all_presets = chatkit_widget_get_all_presets();
?>

         
         


 
      <?php      
            
        echo '<div id="ckw-theme-editor" style="margin-top:16px;padding:12px;border:1px solid #ccd0d4;border-radius:4px;background:#f8f9fa;">
  <h3 style="margin-top:0;">Theme editor (JSON helper)</h3>
  <p class="description">
    These controls only modify the <code>theme</code> section of the JSON above.
    All other settings remain untouched. If the theme section is missing, a default one will be created.
  </p>

  <div style="display:flex;flex-wrap:wrap;gap:16px;margin-top:8px;">
    <div>
      <label for="ckw-theme-scheme"><strong>Color scheme</strong></label><br>
      <select id="ckw-theme-scheme">
        <option value="light">light</option>
        <option value="dark">dark</option>
      </select>
    </div>

    <div>
      <label for="ckw-theme-accent"><strong>Accent primary</strong></label><br>
      <input type="color" id="ckw-theme-accent" value="#204a87">
    </div>

    <div>
      <label for="ckw-theme-bg"><strong>Surface background</strong></label><br>
      <input type="color" id="ckw-theme-bg" value="#e9b96e">
    </div>

    <div>
      <label for="ckw-theme-fg"><strong>Surface foreground</strong></label><br>
      <input type="color" id="ckw-theme-fg" value="#202020">
    </div>

    <div>
      <label for="ckw-theme-hue"><strong>Grayscale hue</strong></label><br>
      <input type="number" id="ckw-theme-hue" class="small-text" min="0" max="360" value="219">
      <span class="description" style="display:block;">0–360</span>
    </div>

    <div>
      <label for="ckw-theme-tint"><strong>Grayscale tint</strong></label><br>
      <input type="number" id="ckw-theme-tint" class="small-text" min="0" max="9" value="0">
      <span class="description" style="display:block;">0–9 (use 0 if unsure)</span>
    </div>
  </div>

  <p id="ckw-theme-status" class="description" style="margin-top:8px;"></p>
</div>
';    
            
           echo ''; 
            
            
            ?>
            
            <script>
(function() {
  var textarea = document.getElementById('chatkit_widget_default_options');
  var editor   = document.getElementById('ckw-theme-editor');
  if (!textarea || !editor) return;

  var schemeEl = document.getElementById('ckw-theme-scheme');
  var accentEl = document.getElementById('ckw-theme-accent');
  var bgEl     = document.getElementById('ckw-theme-bg');
  var fgEl     = document.getElementById('ckw-theme-fg');
  var hueEl    = document.getElementById('ckw-theme-hue');
  var tintEl   = document.getElementById('ckw-theme-tint');
  var statusEl = document.getElementById('ckw-theme-status');

  function safeParse(jsonText) {
    if (!jsonText || !jsonText.trim()) return {};
    try {
      return JSON.parse(jsonText);
    } catch (e) {
      return null;
    }
  }

  function ensureTheme(config) {
    if (!config.theme || typeof config.theme !== 'object') {
      config.theme = {};
    }
    var theme = config.theme;

    if (!theme.colorScheme) {
      theme.colorScheme = 'light';
    }

    if (!theme.color || typeof theme.color !== 'object') {
      theme.color = {};
    }
    var color = theme.color;

    if (!color.grayscale || typeof color.grayscale !== 'object') {
      color.grayscale = { hue: 219, tint: 0 };
    } else {
      if (typeof color.grayscale.hue !== 'number') {
        color.grayscale.hue = 219;
      }
      if (typeof color.grayscale.tint !== 'number') {
        color.grayscale.tint = 0;
      }
    }

    if (!color.accent || typeof color.accent !== 'object') {
      color.accent = { primary: '#204a87', level: 1 };
    } else {
      if (typeof color.accent.primary !== 'string') {
        color.accent.primary = '#204a87';
      }
      if (typeof color.accent.level !== 'number') {
        color.accent.level = 1;
      }
    }

    if (!color.surface || typeof color.surface !== 'object') {
      color.surface = { background: '#e9b96e', foreground: '#202020' };
    } else {
      if (typeof color.surface.background !== 'string') {
        color.surface.background = '#e9b96e';
      }
      if (typeof color.surface.foreground !== 'string') {
        color.surface.foreground = '#202020';
      }
    }

    return config;
  }

  function loadControlsFromJson() {
    var cfg = safeParse(textarea.value);
    if (!cfg) {
      if (statusEl) {
        statusEl.textContent = 'Theme editor: JSON is invalid. Fix the JSON above to use these controls.';
      }
      return;
    }
    cfg = ensureTheme(cfg);

    var t   = cfg.theme;
    var col = t.color;
    var gs  = col.grayscale;
    var ac  = col.accent;
    var sf  = col.surface;

    if (schemeEl) schemeEl.value = (t.colorScheme === 'dark') ? 'dark' : 'light';
    if (accentEl && typeof ac.primary === 'string') accentEl.value = ac.primary;
    if (bgEl && typeof sf.background === 'string')  bgEl.value = sf.background;
    if (fgEl && typeof sf.foreground === 'string')  fgEl.value = sf.foreground;
    if (hueEl && typeof gs.hue === 'number')        hueEl.value = gs.hue;
    if (tintEl && typeof gs.tint === 'number')      tintEl.value = gs.tint;

    if (statusEl) {
      statusEl.textContent = 'Theme editor is synced with JSON.';
    }

    // Write back (ensures theme section exists in JSON)
    textarea.value = JSON.stringify(cfg, null, 2);
  }

  function writeControlsToJson() {
    var cfg = safeParse(textarea.value);
    if (!cfg) {
      if (statusEl) {
        statusEl.textContent = 'Theme editor: JSON is invalid. Changes not applied.';
      }
      return;
    }
    cfg = ensureTheme(cfg);

    var t   = cfg.theme;
    var col = t.color;
    var gs  = col.grayscale;
    var ac  = col.accent;
    var sf  = col.surface;

    if (schemeEl) {
      t.colorScheme = (schemeEl.value === 'dark') ? 'dark' : 'light';
    }

    if (accentEl && accentEl.value) {
      ac.primary = accentEl.value;
    }

    if (bgEl && bgEl.value) {
      sf.background = bgEl.value;
    }

    if (fgEl && fgEl.value) {
      sf.foreground = fgEl.value;
    }

    var hueVal  = parseInt(hueEl && hueEl.value, 10);
    if (!isNaN(hueVal)) {
      gs.hue = hueVal;
    }

    var tintVal = parseInt(tintEl && tintEl.value, 10);
    if (!isNaN(tintVal)) {
      gs.tint = tintVal;
    }

    textarea.value = JSON.stringify(cfg, null, 2);

    if (statusEl) {
      statusEl.textContent = 'Theme updated in JSON.';
    }
  }

  function bind(el) {
    if (!el) return;
    el.addEventListener('change', writeControlsToJson);
    el.addEventListener('input',  writeControlsToJson);
  }

  bind(schemeEl);
  bind(accentEl);
  bind(bgEl);
  bind(fgEl);
  bind(hueEl);
  bind(tintEl);

  // If user manually edits textarea JSON, we can resync the controls on blur
  textarea.addEventListener('blur', loadControlsFromJson);

  // Initial sync
  loadControlsFromJson();
})();
</script>

            
            <?php
            
            
            
            
            
            
            
    }, 'chatkit_widget', 'chatkit_widget_main');

    add_settings_field(
	    'chatkit_widget_custom_css',
	    'Custom front-end CSS',
	    function () {
		$css = get_option('chatkit_widget_custom_css', 'openai-chatkit{display:block;min-height:560px;border-radius:14px;overflow:hidden;}');
		echo '<textarea name="chatkit_widget_custom_css" rows="10" style="width:100%;font-family:monospace;">'
		     . esc_textarea($css)
		     . '</textarea>';
		echo '<p class="description">Optional. Extra CSS applied to the ChatKit shell (.ckw-launcher, .ckw-panel, etc.).</p>';
		
	    },
	    'chatkit_widget',
	    'chatkit_widget_main'
	);
});


if ( ! function_exists( 'mychatkit_sanitize_custom_css' ) ) {
    /**
     * Sanitize custom CSS from settings.
     *
     * We just ensure it's a string and trim whitespace. No HTML is allowed here.
     */
    function mychatkit_sanitize_custom_css( $value ) {
        if ( is_array( $value ) ) {
            $value = implode( "\n", $value );
        }
        $value = (string) $value;

        // No stripslashes() – use wp_unslash if WordPress has magic quotes involved.
        $value = wp_unslash( $value );

        return trim( $value );
    }
}


// Called from admin_init
function chatkit_widget_register_settings() {
    // ...  existing settings ...

    // Presets: stored as PHP array (WP serializes)
    register_setting(
        'chatkit_widget',
        'chatkit_widget_presets',
        [
            'type'              => 'array',
            'sanitize_callback' => 'chatkit_widget_sanitize_presets',
            'default'           => [],
        ]
    );
}
add_action('admin_init', 'chatkit_widget_register_settings');

function chatkit_widget_sanitize_presets($raw) {
    // We expect $raw to be JSON string from hidden field; convert to array.
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
    } else {
        $decoded = $raw;
    }
    if (!is_array($decoded)) {
        return [];
    }

    $clean = [];
    foreach ($decoded as $key => $preset) {
        $key = sanitize_key($key);
        if (!$key) continue;

        $label = isset($preset['label']) ? sanitize_text_field($preset['label']) : '';
        $json  = isset($preset['json']) ? (string) $preset['json'] : '';

        if ($label === '' || $json === '') continue;
        $clean[$key] = [
            'label' => $label,
            'json'  => $json,
        ];
    }
    return $clean;
}

function chatkit_widget_get_builtin_presets() {
    return [
        'soft_light' => [
            'label' => 'Soft light (default)',
            'json'  => wp_json_encode([
                'theme' => [
                    'colorScheme' => 'light',
                    'radius'      => 'round',
                    'density'     => 'normal',
                    'color'       => [
                        'grayscale' => [
                            'hue'  => 219,
                            'tint' => 0,
                        ],
                        'accent' => [
                            'primary' => '#204a87',
                            'level'   => 1,
                        ],
                        'surface' => [
                            'background' => '#e9b96e',
                            'foreground' => '#202020',
                        ],
                    ],
                ],
            ], JSON_PRETTY_PRINT),
        ],
        'soft_dark' => [
            'label' => 'Soft dark',
            'json'  => wp_json_encode([
                'theme' => [
                    'colorScheme' => 'dark',
                    'radius'      => 'round',
                    'density'     => 'normal',
                    'color'       => [
                        'grayscale' => [
                            'hue'  => 219,
                            'tint' => 10,
                        ],
                        'accent' => [
                            'primary' => '#38bdf8',
                            'level'   => 1,
                        ],
                        'surface' => [
                            'background' => '#020617',
                            'foreground' => '#e5e7eb',
                        ],
                    ],
                ],
            ], JSON_PRETTY_PRINT),
        ],
    ];
}

function chatkit_widget_get_all_presets() {
    $builtins = chatkit_widget_get_builtin_presets();
    $stored   = get_option('chatkit_widget_presets', []);
    if (!is_array($stored)) {
        $stored = [];
    }

    // builtins first, then user presets (user keys win if same slug)
    return $builtins + $stored;
}



