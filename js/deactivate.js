jQuery(document).ready(function($) {
    const deactivateLink = $(`tr[data-plugin="${accountLockerDeactivate.pluginSlug}"] .deactivate a`);
    const modal = $('#account-locker-deactivate-modal');

    deactivateLink.on('click', function(e) {
        e.preventDefault();
        modal.show();
    });

    $('#account-locker-cancel').on('click', function() {
        modal.hide();
    });

    $('#account-locker-deactivate').on('click', function() {
        window.location.href = deactivateLink.attr('href');
    });

    $('#account-locker-cleanup').on('click', function() {
        const button = $(this);
        button.prop('disabled', true).text('Processing...');

        $.ajax({
            url: accountLockerDeactivate.ajaxUrl,
            type: 'POST',
            data: {
                action: 'account_locker_cleanup',
                nonce: accountLockerDeactivate.nonce
            },
            success: function() {
                window.location.href = deactivateLink.attr('href');
            },
            error: function() {
                alert('Error cleaning up plugin data');
                button.prop('disabled', false).text('Remove Data & Deactivate');
            }
        });
    });

    $(document).on('click', function(e) {
        if ($(e.target).is(modal)) {
            modal.hide();
        }
    });
}); 