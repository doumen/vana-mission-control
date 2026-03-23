/**
 * VanaAgendaController.js
 *
 * Single responsibility: Agenda Drawer (open/close, day tabs, events).
 * Data source: SSR markup only - no AJAX, no fetch.
 *
 * @package Vana Mission Control
 */

( function () {
    'use strict';

    const DRAWER_SEL = '[data-vana-agenda-drawer]';
    const OVERLAY_SEL = '[data-vana-agenda-overlay]';
    const TAB_SEL = '[data-vana-day-tab]';
    const EVENT_SEL = '[data-vana-event]';
    const OPEN_BTN_SEL = '[data-vana-agenda-open]';
    const CLOSE_BTN_SEL = '[data-vana-agenda-close]';
    const PANEL_SEL = '.vana-agenda-events';

    let isOpen = false;
    let activeDay = null;
    let lastFocused = null;

    function emit( name, detail ) {
        document.dispatchEvent( new CustomEvent( name, {
            bubbles: true,
            detail: detail || {},
        } ) );
    }

    function getEls() {
        const drawer = document.querySelector( DRAWER_SEL );
        const overlay = document.querySelector( OVERLAY_SEL );
        const openBtn = document.querySelector( OPEN_BTN_SEL );
        const closeBtn = drawer ? drawer.querySelector( CLOSE_BTN_SEL ) : null;
        const tabs = drawer ? Array.from( drawer.querySelectorAll( TAB_SEL ) ) : [];
        const events = drawer ? Array.from( drawer.querySelectorAll( EVENT_SEL ) ) : [];
        const panels = drawer ? Array.from( drawer.querySelectorAll( PANEL_SEL ) ) : [];

        return { drawer, overlay, openBtn, closeBtn, tabs, events, panels };
    }

    function getFocusable( root ) {
        if ( ! root ) return [];
        return Array.from(
            root.querySelectorAll(
                'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
            )
        ).filter( el => el.offsetParent !== null );
    }

    function trapFocus( drawerEl ) {
        if ( ! drawerEl ) return;

        drawerEl.addEventListener( 'keydown', function ( e ) {
            if ( e.key !== 'Tab' ) return;

            const focusable = getFocusable( drawerEl );
            if ( focusable.length === 0 ) return;

            const first = focusable[0];
            const last = focusable[focusable.length - 1];

            if ( e.shiftKey && document.activeElement === first ) {
                e.preventDefault();
                last.focus();
                return;
            }

            if ( ! e.shiftKey && document.activeElement === last ) {
                e.preventDefault();
                first.focus();
            }
        } );
    }

    function setVisibility( el, visible ) {
        if ( ! el ) return;
        if ( visible ) {
            el.hidden = false;
            el.removeAttribute( 'hidden' );
        } else {
            el.hidden = true;
            el.setAttribute( 'hidden', '' );
        }
    }

    function openDrawer() {
        const { drawer, overlay, openBtn, closeBtn } = getEls();
        if ( ! drawer ) return;

        lastFocused = document.activeElement;

        setVisibility( drawer, true );
        setVisibility( overlay, true );
        drawer.classList.add( 'is-open' );
        if ( overlay ) overlay.classList.add( 'is-open' );
        document.body.style.overflow = 'hidden';

        if ( openBtn ) openBtn.setAttribute( 'aria-expanded', 'true' );

        isOpen = true;
        activeDay = activeDay || ( drawer.querySelector( TAB_SEL )?.dataset.vanaDayTab || null );

        if ( closeBtn ) {
            closeBtn.focus();
        } else {
            drawer.focus();
        }

        emit( 'vana:agenda:open', {
            visitId: document.querySelector( '.vana-visit' )?.dataset.visitId || null,
        } );
    }

    function closeDrawer() {
        const { drawer, overlay, openBtn } = getEls();
        if ( ! drawer ) return;

        drawer.classList.remove( 'is-open' );
        if ( overlay ) overlay.classList.remove( 'is-open' );
        setVisibility( drawer, false );
        setVisibility( overlay, false );
        document.body.style.overflow = '';

        if ( openBtn ) openBtn.setAttribute( 'aria-expanded', 'false' );

        isOpen = false;

        if ( lastFocused && typeof lastFocused.focus === 'function' ) {
            lastFocused.focus();
        }

        emit( 'vana:agenda:close', {} );
    }

    function activateDay( dayId ) {
        const { tabs, panels } = getEls();
        if ( ! dayId || tabs.length === 0 ) return;

        tabs.forEach( tab => {
            const isActive = tab.dataset.vanaDayTab === dayId;
            tab.classList.toggle( 'vana-agenda-day-tab--active', isActive );
            tab.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
        } );

        panels.forEach( panel => {
            const panelId = panel.id;
            const shouldShow = tabs.some(
                tab => tab.dataset.vanaDayTab === dayId && tab.getAttribute( 'aria-controls' ) === panelId
            );

            if ( shouldShow ) {
                panel.classList.remove( 'hidden' );
            } else {
                panel.classList.add( 'hidden' );
            }
        } );

        activeDay = dayId;
        emit( 'vana:agenda:day:change', {
            dayId: dayId,
            date: dayId,
        } );
    }

    function handleEventClick( eventEl ) {
        if ( ! eventEl ) return;

        const eventKey = eventEl.dataset.vanaEvent || null;
        emit( 'vana:agenda:event:click', {
            eventKey: eventKey,
            dayId: activeDay,
        } );

        if ( eventKey ) {
            const selectorBtn = document.querySelector(
                '[data-vana-event-key="' + CSS.escape( eventKey ) + '"]'
            );
            if ( selectorBtn ) {
                selectorBtn.click();
            }
        }

        closeDrawer();
    }

    function bindOpenClose() {
        const { openBtn, closeBtn, overlay, drawer } = getEls();
        if ( ! drawer ) return;

        if ( openBtn ) {
            openBtn.addEventListener( 'click', function ( e ) {
                e.preventDefault();
                openDrawer();
            } );
        }

        if ( closeBtn ) {
            closeBtn.addEventListener( 'click', function ( e ) {
                e.preventDefault();
                closeDrawer();
            } );
        }

        if ( overlay ) {
            overlay.addEventListener( 'click', closeDrawer );
        }

        document.addEventListener( 'keydown', function ( e ) {
            if ( e.key === 'Escape' && isOpen ) {
                closeDrawer();
            }
        } );
    }

    function bindTabs() {
        const { tabs } = getEls();
        if ( tabs.length === 0 ) return;

        tabs.forEach( tab => {
            tab.addEventListener( 'click', function () {
                activateDay( tab.dataset.vanaDayTab || null );
            } );
        } );

        const first = tabs.find( tab => tab.classList.contains( 'vana-agenda-day-tab--active' ) ) || tabs[0];
        if ( first ) {
            activateDay( first.dataset.vanaDayTab || null );
        }
    }

    function bindEvents() {
        const { events } = getEls();
        if ( events.length === 0 ) return;

        events.forEach( btn => {
            btn.addEventListener( 'click', function () {
                handleEventClick( btn );
            } );
        } );
    }

    function init() {
        const { drawer } = getEls();
        if ( ! drawer ) return;

        drawer.setAttribute( 'tabindex', '-1' );
        trapFocus( drawer );
        bindOpenClose();
        bindTabs();
        bindEvents();
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }
} )();
