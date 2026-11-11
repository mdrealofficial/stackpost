<x-ui.shell
    :title="$title ?? null"
    :shell-area="$shellArea ?? null"
    :full-workspace="$fullWorkspace ?? false"
    :full-workspace-padding-bottom="$fullWorkspacePaddingBottom ?? true"
    :show-loading-backdrop="$showLoadingBackdrop ?? true"
>
    {{ $slot }}
</x-ui.shell>
