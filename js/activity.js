jQuery(document).ready(function($) {
    // Date range validation
    $('input[name="date_to"]').on('change', function() {
        var dateFrom = $('input[name="date_from"]').val();
        var dateTo = $(this).val();
        
        if (dateFrom && dateTo && dateFrom > dateTo) {
            alert('The "To" date must be after the "From" date');
            $(this).val('');
        }
    });

    // Clear individual filters
    $('.clear-filter').on('click', function(e) {
        e.preventDefault();
        var filterName = $(this).data('filter');
        $('[name="' + filterName + '"]').val('');
        $(this).closest('form').submit();
    });

    $('#activity-log-table').DataTable({
        responsive: true,
        order: [[0, 'desc']], // Sort by date descending by default
        pageLength: 15,
        language: {
            search: 'Search:',
            lengthMenu: 'Show _MENU_ entries per page',
            info: 'Showing _START_ to _END_ of _TOTAL_ entries',
            infoEmpty: 'Showing 0 to 0 of 0 entries',
            infoFiltered: '(filtered from _MAX_ total entries)',
            emptyTable: 'No activity found',
            zeroRecords: 'No matching records found'
        },
        columns: [
            { // Date
                type: 'num',
                render: {
                    display: function(data, type, row) {
                        return row[0];
                    },
                    sort: function(data, type, row) {
                        return $(row[0]).data('sort');
                    }
                }
            },
            null, // User
            null, // Action
            null  // Performed By
        ],
        dom: '<"top"lf>rt<"bottom"ip><"clear">',
        responsive: {
            details: {
                type: 'column',
                target: 'tr'
            }
        }
    });
}); 