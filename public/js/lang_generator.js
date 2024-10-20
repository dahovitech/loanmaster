$(document).ready(function() {
    $('form').submit(function(e) {
        e.preventDefault();

        var url_lang_generator = $('#lang-generator').data('url');
        var translate_text = $('#translate-text').data('url');
        var url_create_yaml_file = $('#url_create_yaml_file').data('url');

        var base_language = $('select[name="form[base_language]"]').val();
        var local_language = $('select[name="form[local_language]"]').val();

        // Disable the submit button and show the loading spinner
        var submitButton = $('button[type="submit"]');
        submitButton.prop('disabled', true).text('Loading...');

        $.ajax({
            url: url_lang_generator,
            type: 'POST',
            data: JSON.stringify({
                base_language: base_language,
                local_language: local_language
            }),
            contentType: 'application/json',
            success: function(data) {
                var keys = Object.keys(data);
                var promises = [];

                // Translate each text in the base language
                keys.forEach(function(key) {
                    var text = data[key];
                    var promise = $.ajax({
                        url: translate_text,
                        type: 'POST',
                        data: JSON.stringify({
                            text: text,
                            locale: local_language,
                            source: base_language
                        }),
                        contentType: 'application/json'
                    }).done(function(translatedText) {
                        data[key] = translatedText;
                    }).fail(function() {
                        console.error("Translation failed for key: " + key);
                    });
                    promises.push(promise);
                });

                // When all translations are done
                $.when.apply($, promises).done(function() {
                    $.ajax({
                        url: url_create_yaml_file,
                        type: 'POST',
                        data: JSON.stringify({
                            data: data,
                            local_language: local_language
                        }),
                        contentType: 'application/json',
                        success: function() {
                            swal("Success", "YAML file created successfully!", "success");
                        },
                        error: function() {
                            swal("Error", "Failed to create YAML file.", "error");
                        },
                        complete: function() {
                            // Re-enable the submit button and restore the original text
                            submitButton.prop('disabled', false).text('Submit');
                        }
                    });
                });
            },
            error: function() {
                swal("Error", "Failed to retrieve translations.", "error");
                submitButton.prop('disabled', false).text('Submit');
            }
        });
    });
});
