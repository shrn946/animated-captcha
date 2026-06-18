(function($) {
    'use strict';

    class AnimatedCaptcha {
        constructor(container) {
            this.$container = $(container);
            this.$canvasWrapper = this.$container.find('.canvas-wrapper');
            this.canvas = this.$container.find('canvas')[0];
            this.ctx = this.canvas.getContext('2d');
            this.txt = this.$container.attr('data-captcha-word') || 'CapTchA';
            this.isAnimating = true;
            this.size = parseInt(this.$container.attr('data-size')) || 7;
            this.forcedWidth = parseInt(this.$container.attr('data-width'));

            this.init();
            this.bindEvents();
        }

        init() {
            if (this.forcedWidth) {
                this.width = this.forcedWidth;
                this.height = Math.max(100, this.forcedWidth / 3); // Maintain aspect ratio or min height
                this.unite = this.width / (this.txt.length + 3);
            } else {
                this.unite = 40; // Base unit
                this.width = this.unite * (this.txt.length + 3);
                this.height = this.unite * 3;
            }

            this.canvas.width = this.width;
            this.canvas.height = this.height;
            this.drawFrame();
        }

        bindEvents() {
            this.$canvasWrapper.on('click', (e) => {
                e.preventDefault();
                this.refresh();
            });
        }

        refresh() {
            this.$canvasWrapper.css('opacity', '0.5');
            $.ajax({
                url: wpcf7AnimatedCaptcha.ajax_url,
                type: 'GET',
                data: {
                    action: 'refresh_animated_captcha',
                    nonce: wpcf7AnimatedCaptcha.nonce,
                    size: this.size
                },
                success: (response) => {
                    if (response.success) {
                        this.txt = response.data.captcha_word;
                        this.$container.attr('data-captcha-word', this.txt);
                        this.$container.find('.wpcf7-animated-captcha-session').val(response.data.session_id);
                        this.init();
                        this.$container.find('.wpcf7-animated-captcha-input').val('');
                    }
                },
                complete: () => {
                    this.$canvasWrapper.css('opacity', '1');
                }
            });
        }

        drawFrame() {
            if (!this.isAnimating) return;

            this.ctx.clearRect(0, 0, this.width, this.height);
            
            let i = 0;
            while (++i < 6) {
                this.drawPhase();
            }
            this.drawPhase('#454545');

            this.animationTimeout = setTimeout(() => this.drawFrame(), 80);
        }

        drawPhase(fill) {
            this.ctx.fillStyle = fill || (Math.random() > 0.5 ? '#0cf0cf' : '#454545');
            this.ctx.textAlign = 'center';
            this.drawWord(this.txt);
            this.drawNoise(5);
        }

        drawWord(word) {
            for (let i = 0; i < word.length; i++) {
                this.putText(this.unite * (i + 2), this.height / 2, word.charAt(i), this.unite * 1.5, 0.01);
            }
        }

        drawNoise(noise) {
            for (let i = 0; i < noise; i++) {
                let char = Math.random() > 0.5 ? '+' : Math.random().toString(32)[3];
                this.putText(Math.random() * this.width, Math.random() * this.height, char, this.unite * 1.5 * Math.random());
            }
        }

        putText(x, y, text, size, rotate) {
            if (rotate) {
                rotate += (Math.random() * 0.5) - 0.25;
            }
            this.ctx.font = (Math.random() > 0.5 ? 'bold ' : '') + size + "px 'Cutive Mono', monospace";
            this.ctx.save();
            this.ctx.translate(x + (Math.random() * 5) - 2.5, y + (Math.random() * 5) - 2.5);
            this.ctx.rotate(rotate || Math.random() - 0.5);
            this.ctx.fillText(text, 0, (size * 0.75) / 2);
            this.ctx.restore();
        }

        stop() {
            this.isAnimating = false;
            clearTimeout(this.animationTimeout);
        }
    }

    // Initialize all captchas on the page
    function initCaptchas() {
        $('.wpcf7-animated-captcha-container').each(function() {
            if (!$(this).data('captcha-instance')) {
                let instance = new AnimatedCaptcha(this);
                $(this).data('captcha-instance', instance);
            }
        });
    }

    $(document).ready(function() {
        initCaptchas();
    });

    // Refresh CAPTCHA on CF7 invalid submission
    $(document).on('wpcf7invalid', function(event) {
        $('.wpcf7-animated-captcha-container').each(function() {
            let instance = $(this).data('captcha-instance');
            if (instance) {
                instance.refresh();
            }
        });
    });

})(jQuery);
