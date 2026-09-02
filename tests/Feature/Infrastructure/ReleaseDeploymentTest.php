<?php

it('deploys Coolify only after Release Please creates a release', function () {
    $workflow = file_get_contents(base_path('.github/workflows/release-please.yml'));

    expect($workflow)
        ->toContain('id: release')
        ->toContain("if: \${{ steps.release.outputs.release_created == 'true' }}")
        ->toContain('COOLIFY_WEBHOOK: ${{ secrets.COOLIFY_WEBHOOK }}')
        ->toContain('COOLIFY_TOKEN: ${{ secrets.COOLIFY_TOKEN }}')
        ->toContain('curl --fail-with-body --silent --show-error --request POST')
        ->toContain('Authorization: Bearer $COOLIFY_TOKEN');
});
