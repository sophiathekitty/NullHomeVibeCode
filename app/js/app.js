/**
 * NullHome — frontend SPA logic (jQuery)
 *
 * Communicates with the JSON API at /api/{resource}/…
 * All API calls use the consistent envelope: { success, data, error }
 */
$(function () {

    // ── Navigation ──────────────────────────────────────────────────────────
    $('nav button[data-section]').on('click', function () {
        var target = $(this).data('section');
        $('nav button').removeClass('active');
        $(this).addClass('active');
        $('.section').removeClass('active');
        $('#section-' + target).addClass('active');
        if (target === 'lights') loadLights();
    });

    // Activate the default section
    $('nav button[data-section]').first().trigger('click');

    // ── Helpers ─────────────────────────────────────────────────────────────
    function api(method, path, data, done) {
        var options = {
            url: '/api/' + path,
            type: method,
            contentType: 'application/json',
            success: function (res) {
                if (res.success) {
                    if (done) done(res.data);
                } else {
                    toast('Error: ' + (res.error || 'unknown'), true);
                }
            },
            error: function () {
                toast('Network error', true);
            }
        };
        if (data !== undefined && data !== null) {
            options.data = JSON.stringify(data);
        }
        $.ajax(options);
    }

    function toast(msg, isError) {
        var $t = $('#toast');
        $t.text(msg)
          .css('border-left-color', isError ? 'var(--danger)' : 'var(--on-color)')
          .fadeIn(200);
        clearTimeout($t.data('timer'));
        $t.data('timer', setTimeout(function () { $t.fadeOut(300); }, 2500));
    }

    // ── Lights ──────────────────────────────────────────────────────────────
    function loadLights() {
        api('GET', 'lights', null, function (lights) {
            var $grid = $('#lights-grid').empty();
            if (!lights || lights.length === 0) {
                $grid.html('<p class="empty">No lights added yet.</p>');
                return;
            }
            $.each(lights, function (_, light) {
                $grid.append(buildLightCard(light));
            });
        });
    }

    function buildLightCard(light) {
        var isOn   = parseInt(light.state) === 1;
        var bright = parseInt(light.brightness) || 100;
        var $card  = $('<div class="card" data-id="' + light.id + '">');

        $card.append(
            $('<div class="card-title">').append(
                $('<span class="state-dot">').toggleClass('on', isOn),
                ' ',
                document.createTextNode(light.name)
            )
        );

        if (light.location) {
            $card.append($('<div class="card-sub">').text(light.location));
        }

        // Toggle button
        var $toggle = $('<button class="toggle-btn ' + (isOn ? 'on' : 'off') + '">')
            .html(isOn ? '&#9728; On' : '&#9215; Off')
            .on('click', function () {
                api('POST', 'lights/' + light.id + '/toggle', {}, function (updated) {
                    replaceCard(updated);
                    toast(updated.name + ' toggled ' + (parseInt(updated.state) ? 'on' : 'off'));
                });
            });

        // Delete button
        var $del = $('<button class="btn btn-danger">').text('Remove')
            .on('click', function () {
                if (!confirm('Remove ' + light.name + '?')) return;
                api('DELETE', 'lights/' + light.id, null, function () {
                    $card.remove();
                    toast(light.name + ' removed');
                });
            });

        $card.append($('<div class="card-actions">').append($toggle, $del));

        // Brightness slider
        var $slider  = $('<input type="range" min="0" max="100" value="' + bright + '">');
        var $label   = $('<span>').text(bright + '%');
        var sliderTimer;
        $slider.on('input', function () {
            var val = $(this).val();
            $label.text(val + '%');
            clearTimeout(sliderTimer);
            sliderTimer = setTimeout(function () {
                api('POST', 'lights/' + light.id + '/brightness', { value: parseInt(val) }, function () {
                    toast(light.name + ' brightness set to ' + val + '%');
                });
            }, 400);
        });

        $card.append(
            $('<div class="brightness-row">').append($slider, $label)
        );

        return $card;
    }

    function replaceCard(light) {
        var $old = $('#lights-grid [data-id="' + light.id + '"]');
        if ($old.length) {
            $old.replaceWith(buildLightCard(light));
        }
    }

    // Add light form
    $('#add-light-form').on('submit', function (e) {
        e.preventDefault();
        var name     = $.trim($('#light-name').val());
        var location = $.trim($('#light-location').val());
        if (!name) { toast('Name is required', true); return; }
        api('POST', 'lights', { name: name, location: location }, function (light) {
            $('#light-name').val('');
            $('#light-location').val('');
            $('#lights-grid p.empty').remove();
            $('#lights-grid').append(buildLightCard(light));
            toast(light.name + ' added');
        });
    });

});
