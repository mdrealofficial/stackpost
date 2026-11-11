<style>
.channels-page { color:var(--theme-header-text-color); min-width:0; padding:20px 24px 40px; }
.channels-heading { display:flex; align-items:center; justify-content:space-between; gap:16px; }
.channels-heading h1 { margin:0; font-size:28px; font-weight:700; letter-spacing:-.04em; }
.channels-heading p { margin:5px 0 0; font-size:14px; color:var(--theme-muted-text-color); }
.channels-heading-actions { display:flex; align-items:center; gap:10px; flex-shrink:0; }
.channels-summary { display:flex; flex-wrap:wrap; align-items:center; gap:12px 24px; padding:18px 0; margin-bottom:6px; font-size:12px; color:var(--theme-muted-text-color); }
.channels-summary span { display:inline-flex; align-items:center; gap:6px; }
.channels-summary strong { color:var(--theme-header-text-color); font-weight:700; font-size:15px; }
.channels-enabled-dot { color:var(--theme-success-color); font-size:7px; }
.channels-plan { margin-left:auto; padding:5px 10px; border:1px solid var(--theme-border-color); border-radius:8px; }
.channels-toolbar { padding:16px; border:1px solid var(--theme-card-border-color,var(--theme-border-color)); border-radius:12px; background:var(--theme-surface-base); }
.channels-toolbar-main { display:flex; align-items:center; gap:12px; }
.channels-search { flex:1; min-width:0; }
.channels-toolbar-buttons { display:flex; align-items:center; gap:10px; }
.channels-view-toggle { display:flex; padding:3px; border:1px solid var(--theme-border-color); border-radius:10px; }
.channels-view-toggle button { width:36px; height:34px; border-radius:7px; color:var(--theme-muted-text-color); }
.channels-view-toggle button[aria-pressed=true] { background:var(--theme-surface-soft); color:var(--theme-accent); box-shadow:inset 0 0 0 1px var(--theme-border-color); }
.channels-filter-count { font-size:11px; background:var(--theme-surface-soft); border-radius:5px; padding:1px 5px; }
.channels-filter-fields { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px; padding:16px 0; border-bottom:1px solid var(--theme-border-color); }
.channels-results-bar { display:flex; flex-wrap:wrap; align-items:center; gap:10px; padding-top:12px; font-size:12px; color:var(--theme-muted-text-color); }
.channels-results { flex-shrink:0; }
.channels-active-filters { display:flex; flex-wrap:wrap; align-items:center; gap:6px; min-width:0; }
.channels-active-filters span { padding:3px 7px; border-radius:6px; background:var(--theme-surface-soft); overflow-wrap:anywhere; }
.channels-active-filters button,.channels-clear-selection { color:var(--theme-accent); text-decoration:underline; text-underline-offset:3px; padding:4px; }
.channels-selection-bar { display:flex; flex-wrap:wrap; align-items:center; gap:10px; padding-top:10px; font-size:12px; }
.channels-select-all { display:inline-flex; align-items:center; gap:8px; padding:6px 0; }
.channels-select-all:disabled { opacity:.45; cursor:default; }
.channels-accounts { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px; }
.channel-card { display:flex; flex-direction:column; position:relative; min-width:0; padding:16px; gap:14px; border:1px solid var(--theme-card-border-color,var(--theme-border-color)); border-radius:12px; background:var(--theme-surface-base); transition:border-color .15s,background-color .15s; }
.channel-card[data-selected=true] { border-color:var(--theme-accent); background:color-mix(in srgb,var(--theme-surface-base) 97%,var(--theme-accent)); }
.channel-identity { display:flex; align-items:center; gap:12px; min-width:0; }
.channel-avatar { position:relative; display:grid; place-items:center; width:44px; height:44px; flex-shrink:0; border-radius:50%; background:var(--theme-surface-subtle); }
.channel-avatar-initials { font-size:14px; font-weight:600; color:var(--theme-header-text-color); }
.channel-avatar-image { position:absolute; inset:0; width:100%; height:100%; border-radius:50%; object-fit:cover; background:var(--theme-surface-base); }
.channel-avatar-contained { object-fit:contain; padding:5px; background:white; }
.channel-provider-icon { position:absolute; right:-2px; bottom:-2px; display:grid; place-items:center; width:17px; height:17px; border:2px solid var(--theme-surface-base); border-radius:50%; color:#fff; font-size:8px; }
.channel-name-block { flex:1; min-width:0; }
.channel-name { margin:0; font-size:14px; line-height:21px; font-weight:600; color:var(--theme-header-text-color); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.channel-username { margin:3px 0 0; font-size:12px; line-height:18px; color:var(--theme-muted-text-color); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.channel-meta { display:flex; align-items:center; justify-content:space-between; gap:12px; min-width:0; font-size:11px; line-height:20px; }
.channel-network { color:var(--theme-muted-text-color); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.channel-status { display:inline-flex; align-items:center; gap:5px; flex-shrink:0; color:var(--theme-muted-text-color); }
.channel-status i { width:5px; height:5px; border-radius:50%; background:var(--theme-warning-color); }
.channel-status[data-enabled=true] i { background:var(--theme-success-color); }
.channel-actions { display:flex; align-items:center; justify-content:space-between; gap:12px; border-top:1px solid var(--theme-divider-border-color,var(--theme-border-color)); padding-top:10px; margin-top:auto; min-height:39px; }
.channel-actions>div { margin-left:auto; }
.channel-profile-link { display:inline-flex; align-items:center; gap:8px; font-size:12px; font-weight:500; color:var(--theme-header-text-color); min-height:32px; }
.channel-profile-link i { font-size:10px; color:var(--theme-muted-text-color); }
.channel-profile-link:hover { color:var(--theme-accent); }
.channel-menu-button { width:32px; height:32px; display:grid; place-items:center; border:1px solid var(--theme-border-color); border-radius:8px; background:transparent; color:var(--theme-muted-text-color); }
.channel-menu-button:hover { background:var(--theme-surface-soft); color:var(--theme-header-text-color); }
.channel-select { display:flex; flex-shrink:0; align-items:center; justify-content:center; width:28px; height:32px; cursor:pointer; align-self:flex-start; }

.channels-empty { grid-column:1/-1; }
.channels-page button:focus-visible,.channels-page a:focus-visible,.channel-select input:focus-visible { outline:2px solid var(--theme-accent); outline-offset:3px; }
.channels-page.channels-list .channels-accounts { grid-template-columns:minmax(0,1fr); }
.channels-list .channel-card { display:grid; grid-template-columns:minmax(0,1.3fr) minmax(200px,1fr) 160px; align-items:center; gap:24px; padding:14px 16px; }
.channels-list .channel-actions { margin-top:0; padding-top:0; border-top:0; min-height:32px; }
.channels-list .channel-select { order:-1; align-self:center; }
.channels-toolbar-bottom { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px 20px; padding-top:12px; }
.channels-toolbar-bottom .channels-results-bar,.channels-toolbar-bottom .channels-selection-bar { padding-top:0; }
.channels-toolbar-bottom .channels-results-bar { flex:1; }
.channels-selected-count { padding:4px 7px; color:var(--theme-accent); background:rgba(var(--theme-accent-rgb),.08); border-radius:5px; font-weight:600; }
.channels-selection-bar .channels-bulk-actions { min-height:32px; height:32px; padding:0 10px; font-size:11px; }
.sp-workspace .channels-search input:focus,.sp-workspace .channels-search input:focus-visible { outline:none!important; box-shadow:0 0 0 2px rgba(var(--theme-accent-rgb),.12)!important; border-color:var(--theme-accent)!important; }
@media(min-width:1700px) { .channels-accounts { grid-template-columns:repeat(4,minmax(0,1fr)); } }
@media(max-width:1199px) { .channels-accounts { grid-template-columns:repeat(2,minmax(0,1fr)); } .channels-filter-fields { grid-template-columns:repeat(2,minmax(0,1fr)); } }
@media(min-width:640px) and (max-width:1100px) { .channels-list .channel-card { grid-template-columns:minmax(0,1fr) 150px; gap:10px 20px; }.channels-list .channel-meta { grid-column:1; }.channels-list .channel-actions { grid-column:2;grid-row:1/3; } }
@media(max-width:639px) {
 .channels-page { padding:16px 12px 28px; }
 .channels-heading { align-items:flex-start; flex-wrap:wrap; gap:12px; }
 .channels-heading h1 { font-size:24px; }
 .channels-heading p { font-size:12px; line-height:1.5; }
 .channels-summary { gap:10px 16px; padding:14px 0; }
 .channels-plan { display:none!important; }
 .channels-toolbar { padding:12px; }
 .channels-toolbar-main { flex-wrap:wrap; gap:10px; }
 .channels-search { flex-basis:100%; }
 .channels-toolbar-buttons { justify-content:space-between; width:100%; }
 .channels-filter-fields { grid-template-columns:minmax(0,1fr); }
 .channels-accounts { grid-template-columns:minmax(0,1fr); gap:10px; }
 .channel-card { padding:14px; gap:10px; }
 .channels-list .channel-card { display:flex; flex-direction:column; align-items:stretch; gap:10px; }
 .channels-list .channel-actions { border-top:1px solid var(--theme-divider-border-color); padding-top:10px; }
 .channels-list .channel-actions>a { flex:0 1 auto; }
 .channels-list .channel-handle { margin-top:3px; font-size:12px; }
}

/* Channel connection picker: fixed search above a compact, grouped catalog. */
.channels-picker-body { padding:0!important; overflow:hidden!important; max-height:none!important; }
.channels-picker { display:flex; flex-direction:column; height:min(590px,calc(100dvh - 200px)); min-height:0; color:var(--theme-header-text-color); }
.channels-picker-toolbar { display:flex; align-items:center; gap:14px; flex-shrink:0; padding:14px 20px; border-bottom:1px solid var(--theme-divider-border-color); background:var(--theme-surface-base); }
.channels-picker-search { position:relative; flex:1; min-width:0; }
.channels-picker-search>i { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--theme-muted-text-color); font-size:12px; }
.channels-picker-search input { width:100%; height:38px; border:1px solid var(--theme-control-border-color); border-radius:8px; padding:0 12px 0 34px; font-size:13px; background:var(--theme-input-surface); color:var(--theme-input-text); }
.sp-workspace .channels-picker-search input:focus,.sp-workspace .channels-picker-search input:focus-visible { outline:none; border-color:var(--theme-accent); box-shadow:0 0 0 2px rgba(var(--theme-accent-rgb),.12); }
.channels-picker-result { font-size:11px; color:var(--theme-muted-text-color); white-space:nowrap; }
.channels-picker-result strong { font-weight:600; color:var(--theme-header-text-color); }
.channels-picker-scroll { min-height:0; flex:1; overflow-y:auto; overscroll-behavior:contain; padding:16px 20px 20px; background:var(--theme-app-canvas); }
.channels-picker-group+.channels-picker-group { margin-top:22px; }
.channels-picker-group-heading { display:flex; align-items:center; gap:8px; margin-bottom:12px; }
.channels-picker-group-heading h4 { font-size:12px; font-weight:600; margin:0; }.channels-picker-group-heading>span { color:var(--theme-muted-text-color); font-size:10px; padding:1px 6px; border:1px solid var(--theme-border-color); border-radius:5px; }
.channels-picker-help { color:var(--theme-muted-text-color); font-size:12px; line-height:1.6; margin:-5px 0 12px; }
.channels-picker-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; }
.channels-picker-card { display:flex; flex-direction:column; justify-content:space-between; gap:16px; min-width:0; min-height:118px; padding:14px; border:1px solid var(--theme-card-border-color); border-radius:10px; background:var(--theme-surface-base); }
.channels-picker-card[data-ready=true]:hover { border-color:rgba(var(--theme-accent-rgb),.4); }
.channels-picker-identity { display:flex; align-items:center; gap:10px; min-width:0; }.channels-picker-identity h5 { font-size:13px; font-weight:600; line-height:1.4; margin:0; overflow-wrap:anywhere; }
.channels-picker-icon { display:grid; place-items:center; width:32px; height:32px; flex-shrink:0; border-radius:9px; font-size:14px; }
.channels-picker-actions { display:flex; flex-wrap:wrap; gap:6px; }.channels-picker-actions button { display:inline-flex; align-items:center; justify-content:center; gap:7px; min-height:32px; flex:1 1 auto; border:1px solid var(--theme-border-color); border-radius:7px; padding:6px 9px; font-size:11px; font-weight:500; color:var(--theme-header-text-color); background:var(--theme-surface-base); }
.channels-picker-actions button:not(:disabled):hover { color:var(--theme-accent); background:var(--theme-surface-soft); border-color:rgba(var(--theme-accent-rgb),.4); }.channels-picker-actions button:disabled { color:var(--theme-muted-text-color); background:var(--theme-surface-soft); cursor:not-allowed; opacity:.65; }
.channels-picker-addon-note{display:flex;align-items:center;gap:8px;margin:0;padding:0 20px 12px;font-size:11px;line-height:1.45;color:var(--theme-muted-text-color)}.channels-picker-addon-note span,.channels-addon-badge{display:inline-flex;align-items:center;border:1px solid color-mix(in srgb,var(--theme-accent) 38%,var(--theme-border-color));border-radius:999px;padding:1px 5px;font-size:8px;font-weight:800;line-height:1;text-transform:uppercase;letter-spacing:.05em;color:var(--theme-accent);background:color-mix(in srgb,var(--theme-accent) 8%,var(--theme-surface-base))}.channels-addon-badge{font-size:7px}
.channels-picker-empty { text-align:center; padding:48px 16px; }.channels-picker-empty>i { font-size:22px; color:var(--theme-muted-text-color); }.channels-picker-empty h4 { margin:14px 0 5px; font-size:15px; font-weight:600; }.channels-picker-empty p { font-size:12px; color:var(--theme-muted-text-color); }.channels-picker-empty button { margin-top:16px; font-size:12px; font-weight:600; color:var(--theme-accent); padding:6px; }
@media(min-width:640px) and (max-width:900px) { .channels-picker-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
@media(max-width:639px) { .channels-picker-grid { grid-template-columns:minmax(0,1fr); gap:10px; }.channels-picker-toolbar { padding:12px 16px;gap:10px; }.channels-picker-scroll { padding:14px 16px 16px; }.channels-picker-card { min-height:110px; padding:12px;gap:12px; }.channels-picker { height:calc(100dvh - 220px); } }


/* Shared x-ui controls with a consistent workspace hierarchy. */
.channels-page{--channels-secondary:color-mix(in srgb,var(--theme-header-text-color) 24%,var(--theme-muted-text-color))}.channels-capacity{display:flex;align-items:baseline;gap:8px;flex-wrap:wrap;color:var(--channels-secondary);font-size:12px}.channels-capacity>strong{color:var(--theme-header-text-color);font-size:17px;font-weight:650;white-space:nowrap}.channels-capacity>strong>span{color:var(--channels-secondary);font-size:13px;font-weight:400}.channels-summary .channels-stat>div,.channels-summary .channels-stat>p,.channels-heading p,.channels-collection-heading p,.channel-username,.channel-network{color:var(--channels-secondary)}
.channels-page{padding:0 0 32px}.channels-heading{margin-bottom:24px;gap:24px;align-items:center;padding:4px 0}.channels-heading-copy{min-width:0}.channels-heading h1{font-size:34px;line-height:1.15;margin:0;letter-spacing:-1.2px;font-weight:700}.channels-heading p{font-size:14px;line-height:1.6;margin-top:9px;max-width:560px}.channels-heading-actions .app-ui-button{font-size:12px;height:42px;padding-inline:20px}
.channels-summary{display:grid;align-items:stretch;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;padding:0;margin:0 0 28px}.channels-summary .channels-stat{position:relative;min-height:130px;padding:17px 20px 16px;border-radius:14px;overflow:hidden;box-shadow:none}.channels-summary .channels-stat::after{content:'';position:absolute;inset:0 auto 0 0;width:3px;background:var(--channels-stat-color);opacity:.76}.channels-stat-top{display:flex;justify-content:space-between;gap:12px;align-items:center;color:var(--theme-muted-text-color);font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase}.channels-stat-top>i{display:grid;place-items:center;width:38px;height:38px;border-radius:12px;color:var(--channels-stat-color);background:var(--channels-stat-surface);font-size:16px;letter-spacing:normal}.channels-summary .channels-stat>strong{display:block;color:var(--theme-header-text-color);font-size:31px;line-height:1.08;letter-spacing:-1.1px;margin:12px 0 7px;font-weight:700}.channels-stat>p{font-size:12px;color:var(--theme-muted-text-color);line-height:1.45}.channels-stat[data-tone=teal]{--channels-stat-color:var(--theme-accent);--channels-stat-surface:color-mix(in srgb,var(--theme-accent) 11%,var(--theme-surface-base))}.channels-stat[data-tone=success]{--channels-stat-color:var(--theme-success-color);--channels-stat-surface:color-mix(in srgb,var(--theme-success-color) 10%,var(--theme-surface-base))}.channels-stat[data-tone=warning]{--channels-stat-color:var(--theme-warning-color);--channels-stat-surface:color-mix(in srgb,var(--theme-warning-color) 11%,var(--theme-surface-base))}.channels-stat[data-tone=violet]{--channels-stat-color:#6d5ce7;--channels-stat-surface:rgba(109,92,231,.10)}.channels-collection-heading{display:flex;justify-content:space-between;align-items:center;gap:18px}.channels-collection-heading h2{font-size:19px;font-weight:650;letter-spacing:-.4px}.channels-collection-heading p{margin-top:5px;font-size:13px;color:var(--theme-muted-text-color)}.channels-plan{display:inline-flex;align-items:center;gap:7px;font-size:11px;color:var(--theme-muted-text-color);background:var(--theme-surface-base);padding:7px 10px}
.channels-toolbar{padding:0 18px 16px;border-radius:var(--theme-card-radius,1rem)}.channels-network-shortcuts{display:flex;gap:6px;align-items:center;flex-wrap:wrap;padding:13px 0;border-bottom:1px solid var(--theme-divider-border-color);margin-bottom:16px}.channels-network-shortcuts .app-ui-button{height:36px;padding:0 12px;font-size:12px;color:var(--channels-secondary)}.channels-network-shortcuts .app-ui-button[aria-pressed=true]{background:color-mix(in srgb,var(--theme-accent) 8%,var(--theme-surface-base));color:var(--theme-accent)}.channels-network-shortcuts .app-ui-button>span{font-size:11px;opacity:1;font-weight:500}.channels-view-toggle .app-ui-button{padding:0!important;display:grid;place-items:center}.channels-view-toggle .app-ui-button[aria-pressed=true]{background:color-mix(in srgb,var(--theme-accent) 8%,var(--theme-surface-base));box-shadow:none}.channels-select-all span.block{font-size:12px;font-weight:500}.channels-toolbar-bottom{padding-top:16px}.channels-selection-bar{gap:12px}.channels-selected-count{padding:5px 8px}.channels-select-all input:focus-visible+span,.channel-select input:focus-visible+span{outline:2px solid var(--theme-accent);outline-offset:3px}.channels-select-all input:indeterminate+span{background:var(--theme-accent);border-color:var(--theme-accent);position:relative}.channels-select-all input:indeterminate+span::after{content:'';width:9px;height:2px;background:white;position:absolute}.channels-select-all input:disabled+span{opacity:.45}
.channels-accounts{gap:20px}.channels-page .channel-card{padding:18px;gap:12px;border-radius:var(--theme-card-radius,1rem);box-shadow:none}.channels-page .channel-card[data-selected=true]{border-color:var(--theme-accent)!important;background-color:color-mix(in srgb,var(--theme-surface-base) 97%,var(--theme-accent))!important}.channel-avatar{width:50px;height:50px}.channel-provider-icon{width:20px;height:20px;font-size:10px}.channel-name{font-size:15px;line-height:22px}.channel-username{font-size:13px}.channel-network{font-size:12px}.channel-status{font-size:11px;font-weight:500;padding:3px 7px;border-radius:6px;background:var(--theme-surface-soft)}.channel-status[data-enabled=true]{color:var(--theme-accent);background:color-mix(in srgb,var(--theme-accent) 6%,var(--theme-surface-base))}.channel-actions{padding-top:10px;gap:12px}.channel-actions .channel-profile-link{padding:0 7px;margin-left:-7px;height:34px;font-size:12px;font-weight:600;color:var(--theme-header-text-color)}.channel-actions .channel-menu-button{height:34px;width:34px;padding:0;min-height:0;color:var(--theme-muted-text-color)}.channel-select{width:24px;height:28px;margin-left:3px}.channels-page.channels-list .channel-card{padding:17px 20px;gap:24px}.channels-list .channel-avatar{width:44px;height:44px}.channels-list .channel-actions{padding-top:0}.channels-list .channel-meta{justify-content:flex-start;gap:20px}.channels-empty{padding:24px;border:1px solid var(--theme-border-color);background:var(--theme-surface-base);border-radius:14px}.channels-picker-search input{padding-left:40px}.channels-picker-actions .app-ui-button{font-size:11px;height:auto;min-height:34px}.channels-picker-empty .app-ui-button{margin-top:16px}
@media(max-width:900px){.channels-summary{gap:10px}.channels-summary .channels-stat{min-height:124px;padding:14px 16px}.channels-stat-top{font-size:10px}.channels-stat-top>i{width:34px;height:34px;border-radius:10px;font-size:14px}.channels-stat>p{font-size:11px}.channels-summary .channels-stat>strong{font-size:28px}.channels-heading{align-items:flex-start;flex-wrap:wrap}}
@media(max-width:639px){.channels-page{padding:0 0 24px}.channels-heading h1{font-size:28px}.channels-heading p{font-size:12px}.channels-heading-actions{width:100%;flex-wrap:wrap;gap:10px}.channels-heading-actions>.app-ui-button,.channels-heading-actions>div{flex:1}.channels-heading-actions>div .app-ui-button{width:100%}.channels-summary{grid-template-columns:repeat(2,minmax(0,1fr));margin-bottom:24px}.channels-summary .channels-stat{padding:14px 16px}.channels-stat>div{font-size:12px}.channels-stat>p{font-size:11px}.channels-collection-heading{align-items:flex-start;flex-direction:column;gap:10px}.channels-collection-heading p{line-height:1.6}.channels-toolbar{padding:0 12px 14px}.channels-network-shortcuts{flex-wrap:nowrap;overflow-x:auto;max-width:100%;padding-bottom:14px}.channels-network-shortcuts .app-ui-button{flex-shrink:0}.channels-network-shortcuts::after{content:'';min-width:1px}.channels-page .channel-card,.channels-page.channels-list .channel-card{padding:16px;gap:12px}.channels-accounts{gap:12px}.channels-list .channel-actions{padding-top:10px}.channels-toolbar-bottom{gap:14px}.channels-selection-bar{gap:10px}.channels-list .channel-meta{justify-content:space-between}}
@media(prefers-reduced-motion:reduce){.channels-page .channel-card{transition:none}}
.channels-update-banner{margin-bottom:28px}
@media(max-width:639px){.channels-update-banner{margin-bottom:22px}}
</style>
