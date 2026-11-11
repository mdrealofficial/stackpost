{
    providers: @js($providers), canImport: @js($canImport),
    searchEndpoint: @js($searchOnlineEndpoint), uploadEndpoint: @js($uploadFromUrlEndpoint),
    keyword: '', type: 'image', source: '', quality: 'standard', folder: '',
    results: [], selected: [], saved: {}, failures: {}, page: 0, hasMore: false,
    searching: false, saving: false, searched: false, error: '', searchLabel: '',
    completed: 0, total: 0, preview: null, previewError: false, lastSearch: null,
    init() { this.syncSource(); },
    sources() { return Object.entries(this.providers).filter(([key]) => this.type === 'video' ? key.endsWith('_video') : !key.endsWith('_video')); },
    syncSource() { if (!this.sources().some(([key]) => key === this.source)) this.source = this.sources()[0]?.[0] || ''; },
    mediaKey(media) { return media.provider + ':' + media.id; },
    isSelected(media) { return this.selected.some(item => this.mediaKey(item) === this.mediaKey(media)); },
    toggle(media) {
        if (this.saving || this.saved[this.mediaKey(media)]) return;
        this.selected = this.isSelected(media) ? this.selected.filter(item => this.mediaKey(item) !== this.mediaKey(media)) : [...this.selected, media];
    },
    clearSelection() { if (!this.saving) { this.selected = []; this.failures = {}; } },
    notify(type, message) { window.dispatchEvent(new CustomEvent('app-toast', {detail: {type, message}})); },
    async request(endpoint, body) {
        const response = await fetch(endpoint, {
            method: 'POST', credentials: 'same-origin',
            headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || ''},
            body: JSON.stringify(body),
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            const validation = Object.values(payload.errors || {}).flat()[0];
            throw new Error(validation || (response.status === 403 ? 'You do not have permission for this action.' : response.status === 419 ? 'Your session expired. Refresh the page and try again.' : 'The request could not be completed. Please try again.'));
        }
        return payload;
    },
    async searchMedia(more = false) {
        if (this.searching || this.saving) return;
        if (!more && (!this.keyword.trim() || !this.source)) return;
        if (more && (!this.lastSearch || !this.hasMore)) return;
        const query = more ? this.lastSearch : {keyword: this.keyword.trim(), source: this.source, type: this.type, quality: this.quality};
        const nextPage = more ? this.page + 1 : 1;
        this.searching = true; this.error = '';
        if (!more) { this.results = []; this.page = 0; this.hasMore = false; this.searched = false; }
        try {
            const payload = await this.request(this.searchEndpoint, {...query, page: nextPage});
            const incoming = Array.isArray(payload.data) ? payload.data : [];
            const unique = new Map((more ? this.results : []).map(item => [this.mediaKey(item), item]));
            incoming.forEach(item => unique.set(this.mediaKey(item), item));
            this.results = [...unique.values()]; this.page = nextPage; this.hasMore = !!payload.has_more;
            this.lastSearch = query; this.searchLabel = query.keyword; this.searched = true;
        } catch (error) { this.error = error.message || 'Search failed. Please try again.'; }
        finally { this.searching = false; }
    },
    async saveSelected() {
        if (this.saving || !this.selected.length || !this.canImport) return;
        const queue = this.selected.filter(item => !this.saved[this.mediaKey(item)]);
        const destination = this.folder;
        this.saving = true; this.completed = 0; this.total = queue.length;
        let successes = 0;
        for (const media of queue) {
            const key = this.mediaKey(media); delete this.failures[key];
            try {
                const payload = await this.request(this.uploadEndpoint, {
                    remote_url: media.full, type: media.mediaType, folder: destination,
                    source_provider: media.source, source_author: media.author || '', source_page: media.link || null,
                });
                this.saved[key] = payload.data || true;
                this.selected = this.selected.filter(item => this.mediaKey(item) !== key); successes++;
            } catch (error) { this.failures[key] = error.message || 'Could not save this file. Try again.'; }
            finally { this.completed++; }
        }
        this.saving = false;
        const failed = queue.length - successes;
        this.notify(failed ? 'warning' : 'success', `${successes} saved to Files${failed ? ` · ${failed} failed. Retry the remaining items.` : '.'}`);
    },
    openPreview(media) { this.previewError = false; this.preview = media; },
    closePreview() { this.preview = null; },
}
