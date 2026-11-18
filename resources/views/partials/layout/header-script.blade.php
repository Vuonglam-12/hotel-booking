<script>
    (() => {
        const header = document.querySelector('.hv-header');
        const mobileMenu = document.getElementById('mobile-menu');
        const mobileToggle = document.getElementById('mobile-menu-toggle');
        const menuButtons = header.querySelectorAll('[data-shell-menu-toggle]');
        const searchPanel = document.getElementById('header-search-panel');
        const searchEnabled = header.dataset.searchEnabled !== 'false';
        const experiences = document.getElementById('header-experiences');
        const compactToggle = document.getElementById('compact-search-toggle');
        const loginDialog = document.getElementById('customer-login-dialog');
        const loginFrame = document.getElementById('customer-login-frame');
        let searchExpanded = false;
        let scrollScheduled = false;
        let loginOpener = null;
        let loginScrollY = 0;
        let previousBodyStyles = null;

        function measureHeader() {
            if (header.classList.contains('is-compact')) return;
            document.documentElement.style.setProperty('--hv-header-expanded-height', `${Math.ceil(header.getBoundingClientRect().height)}px`);
            document.documentElement.style.setProperty('--hv-search-panel-height', `${Math.ceil(searchPanel.getBoundingClientRect().height)}px`);
        }

        function closeSearchMenus() {
            window.HolidayVietSearch?.closeSuggestions();
            window.HolidayVietSearch?.closePanels?.();
        }

        function updateHeader() {
            scrollScheduled = false;
            if (window.scrollY < 48) searchExpanded = false;
            const threshold = header.classList.contains('is-compact') ? 48 : 136;
            const compact = searchEnabled && window.scrollY > threshold && !searchExpanded && !searchPanel.contains(document.activeElement);
            if (compact) {
                closeNavigationMenus();
                closeSearchMenus();
                measureHeader();
            }
            header.classList.toggle('is-compact', compact);
            searchPanel.inert = !searchEnabled || compact;
            experiences.inert = compact;
            compactToggle.setAttribute('aria-expanded', String(searchEnabled && !compact));
            measureHeader();
        }

        function scheduleHeaderUpdate() {
            if (!scrollScheduled) { scrollScheduled = true; requestAnimationFrame(updateHeader); }
        }

        compactToggle.addEventListener('click', () => {
            searchExpanded = true;
            updateHeader();
            requestAnimationFrame(() => document.getElementById('searchInput').focus({ preventScroll: true }));
        });
        window.addEventListener('scroll', scheduleHeaderUpdate, { passive: true });
        searchPanel.addEventListener('focusout', scheduleHeaderUpdate);
        document.querySelector('[data-guest-menu-links]').hidden = Boolean(localStorage.getItem('token'));

        function setMenuOpen(button, open) {
            button.setAttribute('aria-expanded', String(open));
            document.getElementById(button.getAttribute('aria-controls')).hidden = !open;
        }

        function closeNavigationMenus() {
            menuButtons.forEach(button => setMenuOpen(button, false));
        }

        function closeMobileMenu() {
            mobileMenu.classList.remove('open');
            menuButtons.forEach(button => { if (mobileMenu.contains(button)) setMenuOpen(button, false); });
            mobileToggle.setAttribute('aria-expanded', 'false');
            mobileToggle.setAttribute('aria-label', 'Mở menu điều hướng');
        }

        window.closeShellNavigationMenus = () => {
            closeNavigationMenus();
            closeSearchMenus();
        };
        window.closeShellMobileMenu = closeMobileMenu;

        // Keep the fixed header and page offset in sync with wrapped labels and disclosures.
        new ResizeObserver(measureHeader).observe(header);
        searchPanel.addEventListener('focusin', () => {
            closeNavigationMenus();
            closeMobileMenu();
            closeUserDropdown();
            closeNotificationDropdown();
        });

        function openCustomerLogin(event) {
            event?.preventDefault();
            loginOpener = event?.target.closest('[data-customer-login-open]') || document.activeElement;
            closeNavigationMenus();
            closeMobileMenu();
            closeUserDropdown();
            closeNotificationDropdown();
            const loginUrl = new URL('/login', window.location.origin);
            loginUrl.searchParams.set('popup', '1');
            if (loginFrame.src !== loginUrl.toString()) loginFrame.src = loginUrl.toString();
            loginOpener.setAttribute('aria-expanded', 'true');
            loginScrollY = window.scrollY;
            previousBodyStyles = {
                position: document.body.style.position,
                top: document.body.style.top,
                width: document.body.style.width,
                overflow: document.body.style.overflow
            };
            document.documentElement.classList.add('hv-auth-dialog-open');
            document.body.style.position = 'fixed';
            document.body.style.top = `-${loginScrollY}px`;
            document.body.style.width = '100%';
            document.body.style.overflow = 'hidden';
            loginDialog.showModal();
            loginFrame.focus();
        }

        window.requestCustomerLogin = (message='Vui lòng đăng nhập để sử dụng chức năng này.') => {
            showToast(message);
            openCustomerLogin();
        };

        function restoreLoginScroll() {
            if (!previousBodyStyles) return;
            document.documentElement.classList.remove('hv-auth-dialog-open');
            Object.assign(document.body.style, previousBodyStyles);
            previousBodyStyles = null;
            window.scrollTo(0, loginScrollY);
        }

        function closeCustomerLogin() {
            if (!loginDialog.open) return;
            const opener = loginOpener;
            loginDialog.close();
            loginFrame.src = 'about:blank';
            restoreLoginScroll();
            opener?.setAttribute('aria-expanded', 'false');
            if (opener?.isConnected) opener.focus();
            else document.getElementById('user-menu-toggle')?.focus();
        }

        header.addEventListener('click', event => {
            const trigger = event.target.closest('[data-customer-login-open]');
            if (trigger) openCustomerLogin(event);
        });
        loginDialog.querySelector('[data-customer-login-close]').addEventListener('click', closeCustomerLogin);
        loginDialog.addEventListener('click', event => {
            if (event.target === loginDialog) closeCustomerLogin();
        });
        loginDialog.addEventListener('cancel', event => {
            event.preventDefault();
            closeCustomerLogin();
        });
        loginDialog.addEventListener('close', () => {
            if (loginOpener?.isConnected) loginOpener.focus();
            else document.getElementById('user-menu-toggle')?.focus();
        });
        loginDialog.addEventListener('keydown', event => {
            if (event.key !== 'Tab') return;
            const closeButton = loginDialog.querySelector('[data-customer-login-close]');
            if (event.shiftKey && document.activeElement === closeButton) {
                event.preventDefault();
                loginFrame.focus();
            } else if (!event.shiftKey && document.activeElement === loginFrame) {
                event.preventDefault();
                closeButton.focus();
            }
        });
        window.addEventListener('message', event => {
            if (event.origin !== window.location.origin || event.source !== loginFrame.contentWindow) return;
            if (event.data?.type === 'aurel-authenticated' && event.data.user) {
                window.renderAuthenticatedHeader?.(event.data.user);
                closeCustomerLogin();
                showToast('Đăng nhập thành công.');
            } else if (event.data?.type === 'aurel-auth-invalid') {
                window.renderGuestHeader?.();
            } else if (event.data?.type === 'aurel-close-login') {
                closeCustomerLogin();
            }
        });

        menuButtons.forEach(button => {
            button.addEventListener('click', () => {
                const open = button.getAttribute('aria-expanded') !== 'true';
                closeUserDropdown();
                closeNotificationDropdown();
                closeSearchMenus();
                if (!mobileMenu.contains(button)) closeMobileMenu();
                closeNavigationMenus();
                setMenuOpen(button, open);
            });
        });

        header.addEventListener('click', event => {
            if (event.target.closest('a, #user-dropdown-menu button')) {
                closeMobileMenu();
                closeNavigationMenus();
                closeSearchMenus();
                closeUserDropdown();
                closeNotificationDropdown();
            }
            if (event.target.closest('[data-customer-login-open]')) closeSearchMenus();
        });

        document.addEventListener('click', event => {
            if (!event.target.closest('[data-shell-menu-toggle], .hv-navigation-panel')) closeNavigationMenus();
            if (!header.contains(event.target) && !event.composedPath().includes(header)) {
                closeMobileMenu();
                closeSearchMenus();
                searchExpanded = false;
                if (searchPanel.contains(document.activeElement)) document.activeElement.blur();
                scheduleHeaderUpdate();
            }
        });

        document.addEventListener('keydown', event => {
            if (event.key !== 'Escape') return;

            const openNavigationButton = Array.from(menuButtons).find(button => button.getAttribute('aria-expanded') === 'true');
            const headerFocusTarget = openNavigationButton
                || (_userDropOpen && document.getElementById('user-menu-toggle'))
                || (_notifOpen && document.getElementById('notif-bell'))
                || (mobileMenu.classList.contains('open') && mobileToggle);
            if (headerFocusTarget) {
                closeNavigationMenus();
                if (!openNavigationButton || !mobileMenu.contains(openNavigationButton)) closeMobileMenu();
                closeUserDropdown();
                closeNotificationDropdown();
                requestAnimationFrame(() => headerFocusTarget.focus());
                event.preventDefault();
                event.stopImmediatePropagation();
                return;
            }

            if (HolidayVietSearch.closeSuggestions()) {
                document.getElementById('searchInput').focus({ preventScroll: true });
                event.preventDefault();
                event.stopImmediatePropagation();
                return;
            }
            if (HolidayVietSearch.closePanels?.(true)) {
                event.preventDefault();
                event.stopImmediatePropagation();
                return;
            }
            const focusTarget = (searchExpanded && compactToggle)
                || (searchPanel.contains(document.activeElement) && document.getElementById('searchInput'));
            if (!focusTarget) return;
            searchExpanded = false;
            HolidayVietSearch.closeSuggestions();
            if (searchPanel.contains(document.activeElement)) document.activeElement.blur();
            updateHeader();
            requestAnimationFrame(() => focusTarget.focus());
            event.preventDefault();
            event.stopImmediatePropagation();
        }, true);

        window.addEventListener('resize', () => {
            closeMobileMenu();
            closeNavigationMenus();
            closeUserDropdown();
            closeNotificationDropdown();
            closeSearchMenus();
            scheduleHeaderUpdate();
        });
        updateHeader();
    })();
</script>
