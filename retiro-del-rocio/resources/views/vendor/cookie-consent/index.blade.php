@php
    $locale = $app->getLocale();
@endphp

{{-- Retiro Del Rocio cookie-consent styling (dark theme). JS hooks (js-lcc-*) untouched. --}}
<style>
    .lcc-modal, .lcc-backdrop { box-sizing: border-box; font-family: inherit; }
    .lcc-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,.6); z-index: 9998; }

    /* ---- Bottom alert banner ---- */
    .lcc-modal--alert {
        position: fixed; left: 50%; bottom: 18px; transform: translateX(-50%);
        width: min(1100px, calc(100% - 28px)); z-index: 9999;
        display: flex; flex-direction: column; gap: 16px;
        background: #1b231a; color: #fff;
        border: 1px solid rgba(255,255,255,.08); border-radius: 18px;
        padding: 20px 22px; box-shadow: 0 18px 50px rgba(0,0,0,.45);
    }
    @media (min-width: 900px) {
        .lcc-modal--alert { gap: 18px; padding: 26px 30px; }
    }
    /* Text spans the full width; buttons sit below it. */
    .lcc-modal--alert .lcc-modal__content { width: 100%; }
    .lcc-modal__title { margin: 0 0 6px; font-size: 18px; font-weight: 700; color: #fff; }
    .lcc-text { margin: 0; font-size: 14px; line-height: 1.6; color: rgba(255,255,255,.72); }
    .lcc-text a { color: #f38c00; text-decoration: underline; }
    .lcc-modal__actions { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; flex-shrink: 0; }
    .lcc-modal__actions-center { justify-content: center; }

    .lcc-button {
        cursor: pointer; border: 1px solid transparent; border-radius: 11px;
        padding: 11px 20px; font-size: 14px; font-weight: 600; white-space: nowrap;
        background: #f38c00; color: #fff; transition: background .2s, color .2s, border-color .2s;
    }
    .lcc-button:hover { background: #dd7f00; }
    .lcc-button.js-lcc-essentials { background: transparent; border-color: rgba(255,255,255,.3); color: #fff; }
    .lcc-button.js-lcc-essentials:hover { background: rgba(255,255,255,.08); }
    .lcc-button--ghost { background: transparent; color: #f38c00; padding: 11px 8px; text-decoration: underline; }
    .lcc-button--ghost:hover { background: transparent; color: #dd7f00; }

    /* ---- Settings modal ---- */
    .lcc-modal--settings {
        position: fixed; left: 50%; top: 50%; transform: translate(-50%, -50%);
        width: min(560px, calc(100% - 28px)); max-height: 86vh; overflow-y: auto; z-index: 9999;
        display: block; background: #1b231a; color: #fff;
        border: 1px solid rgba(255,255,255,.08); border-radius: 18px;
        padding: 28px; box-shadow: 0 18px 50px rgba(0,0,0,.5);
    }
    .lcc-modal__close { position: absolute; top: 14px; right: 16px; background: transparent; border: 0; color: #fff; font-size: 26px; line-height: 1; cursor: pointer; opacity: .7; }
    .lcc-modal__close:hover { opacity: 1; }
    .lcc-modal--settings .lcc-modal__title { font-size: 20px; margin-bottom: 14px; }
    .lcc-modal__section { padding: 14px 0; border-top: 1px solid rgba(255,255,255,.1); }
    .lcc-modal__section:first-of-type { border-top: 0; }
    .lcc-label { display: flex; align-items: center; gap: 10px; font-size: 15px; font-weight: 600; color: #fff; cursor: pointer; }
    .lcc-label input { width: 18px; height: 18px; accent-color: #f38c00; }
    .lcc-modal__section .lcc-text { margin-top: 6px; }
    .lcc-u-sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; }
</style>

<div
    role="dialog"
    aria-labelledby="lcc-modal-alert-label"
    aria-describedby="lcc-modal-alert-desc"
    aria-modal="true"
    class="lcc-modal lcc-modal--alert js-lcc-modal js-lcc-modal-alert"
    style="display: none"
    data-cookie-key="{{ config('cookie-consent.cookie_key') }}"
    data-cookie-value-analytics="{{ config('cookie-consent.cookie_value_analytics') }}"
    data-cookie-value-marketing="{{ config('cookie-consent.cookie_value_marketing') }}"
    data-cookie-value-both="{{ config('cookie-consent.cookie_value_both') }}"
    data-cookie-value-none="{{ config('cookie-consent.cookie_value_none') }}"
    data-cookie-expiration-days="{{ config('cookie-consent.cookie_expiration_days') }}"
    data-gtm-event="{{ config('cookie-consent.gtm_event') }}"
    data-ignored-paths="{{ implode(',', config('cookie-consent.ignored_paths', [])) }}"
    data-session-domain="{{ config('session.domain', '') }}"
    data-cookie-secure="{{ config('cookie-consent.cookie_secure', false) }}"
>
    <div class="lcc-modal__content">
        <h2 id="lcc-modal-alert-label" class="lcc-modal__title">
            @lang('cookie-consent::texts.alert_title')
        </h2>
        <p id="lcc-modal-alert-desc" class="lcc-text">
            {!! trans('cookie-consent::texts.alert_text') !!}
        </p>
    </div>
    <div class="lcc-modal__actions">
        <button type="button" class="lcc-button js-lcc-accept">
            @lang('cookie-consent::texts.alert_accept')
        </button>
        <button type="button" class="lcc-button js-lcc-essentials">
            @lang('cookie-consent::texts.alert_essential_only')
        </button>
        <button type="button" class="lcc-button lcc-button--ghost js-lcc-settings-toggle">
            @lang('cookie-consent::texts.alert_settings')
        </button>
    </div>
</div>

<div
    role="dialog"
    aria-labelledby="lcc-modal-settings-label"
    aria-describedby="lcc-modal-settings-desc"
    aria-modal="true"
    class="lcc-modal lcc-modal--settings js-lcc-modal js-lcc-modal-settings"
    style="display: none"
>
    <button class="lcc-modal__close js-lcc-settings-toggle" type="button">
        <span class="lcc-u-sr-only">
            @lang('cookie-consent::texts.settings_close')
        </span>
        &times;
    </button>
    <div class="lcc-modal__content">
        <div class="lcc-modal__content">
            <h2 id="lcc-modal-settings-label" class="lcc-modal__title">
                @lang('cookie-consent::texts.settings_title')
            </h2>
            <p id="lcc-modal-settings-desc" class="lcc-text">
                {!! trans('cookie-consent::texts.settings_text', ['policyUrl' => config("cookie-consent.policy_url_$locale")]) !!}
            </p>
            <div class="lcc-modal__section lcc-u-text-center">
                <button type="button" class="lcc-button js-lcc-accept">
                    @lang('cookie-consent::texts.settings_accept_all')
                </button>
            </div>
            <div class="lcc-modal__section">
                <label for="lcc-checkbox-essential" class="lcc-label">
                    <input type="checkbox" id="lcc-checkbox-essential" disabled="disabled" checked="checked" />
                    <span>@lang('cookie-consent::texts.setting_essential')</span>
                </label>
                <p class="lcc-text">
                    @lang('cookie-consent::texts.setting_essential_text')
                </p>
            </div>
            <div class="lcc-modal__section">
                <label for="lcc-checkbox-functional" class="lcc-label">
                    <input type="checkbox" id="lcc-checkbox-functional" disabled="disabled" checked="checked" />
                    <span>@lang('cookie-consent::texts.setting_functional')</span>
                </label>
                <p class="lcc-text">
                    @lang('cookie-consent::texts.setting_functional_text')
                </p>
            </div>
            <div class="lcc-modal__section">
                <label for="lcc-checkbox-analytics" class="lcc-label">
                    <input type="checkbox" id="lcc-checkbox-analytics" />
                    <span>@lang('cookie-consent::texts.setting_analytics')</span>
                </label>
                <p class="lcc-text">
                    @lang('cookie-consent::texts.setting_analytics_text')
                </p>
            </div>
            <div class="lcc-modal__section">
                <label for="lcc-checkbox-marketing" class="lcc-label">
                    <input type="checkbox" id="lcc-checkbox-marketing" />
                    <span>@lang('cookie-consent::texts.setting_marketing')</span>
                </label>
                <p class="lcc-text">
                    @lang('cookie-consent::texts.setting_marketing_text')
                </p>
            </div>
        </div>
    </div>
    <div class="lcc-modal__actions lcc-modal__actions-center">
        <button type="button" class="lcc-button js-lcc-settings-save">
            @lang('cookie-consent::texts.settings_save')
        </button>
    </div>
</div>

<div class="lcc-backdrop js-lcc-backdrop" style="display: none"></div>
<script type="text/javascript" src="{{ asset('vendor/cookie-consent/js/cookie-consent.js') }}"></script>
