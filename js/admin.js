jQuery(function ($) {
    let frame;

    function openMediaFrame(row) {
        if (frame) {
            frame.off('select');
        }
        frame = wp.media({
            title: 'Select file to replace',
            button: { text: 'Use this file' },
            multiple: false
        });
        frame.on('select', function () {
            const attachment = frame.state().get('selection').first().toJSON();
            row.find('.qfr-file-id').val(attachment.id);
            row.find('.qfr-selected-file').text(attachment.url);
        });
        frame.open();
    }

    $('#qfr-container').on('click', '.qfr-select-media', function (e) {
        e.preventDefault();
        openMediaFrame($(this).closest('.qfr-row'));
    });

    $('#qfr-add-row').on('click', function () {
        const count = $('.qfr-row').length;
        $.post(EFR.ajax, {
            action: 'qfr_add_row',
            index: count,
            nonce: EFR.nonce
        }).done(function (resp) {
            if (resp && resp.success && resp.data && resp.data.html) {
                $('#qfr-container').append(resp.data.html);
            }
        });
    });
});
