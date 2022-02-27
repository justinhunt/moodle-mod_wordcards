/**
 * Module to help with Free mode page.
 *
 * @package mod_wordcards
 * @author David Watson - evolutioncode.uk
 */
define(['jquery'], function($) {
    return {
        init: function () {
            $(document).ready(function() {
                $('#wordpool-selector-btn').on('click', function() {
                    console.log('cl')
                    const content = $('#wordpool-selector-content');
                    if (content.hasClass('show')) {
                        console.log('hide')
                        content.removeClass('show')
                    } else {
                        console.log('s')
                        content.addClass('show')
                    }
                })
            })
        }
    }
})