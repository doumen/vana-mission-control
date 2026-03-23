/**
 * VanaChipController.js
 *
 * Responsabilidade única: chip bar (scroll + highlight de seção).
 * Estratégia: IntersectionObserver + scroll suave + CustomEvents.
 *
 * IMPORTANTE:
 * - Chips NÃO filtram conteúdo
 * - Chips NÃO conhecem tour_id
 * - Chips apenas navegam entre seções da visita atual
 *
 * @package Vana Mission Control
 */

( function () {
    'use strict';

    // 1. Seletores (com fallback para markup legado)
    var BAR_SEL = '[data-vana-chip-bar], #vana-anchor-chips';
    var CHIP_SEL = '[data-vana-chip], .vana-anchor-chip';
    var SECTION_SEL = '[data-vana-section]';

    // 2. Estado interno
    var activeChipId = null;
    var observer = null;

    function emit( name, detail ) {
        document.dispatchEvent( new CustomEvent( name, {
            bubbles: true,
            detail: detail || {},
        } ) );
    }

    function getBar() {
        return document.querySelector( BAR_SEL );
    }

    function getChips( bar ) {
        if ( ! bar ) return [];
        return Array.prototype.slice.call( bar.querySelectorAll( CHIP_SEL ) );
    }

    function getChipId( chip, index ) {
        return chip.dataset.vanaChip || chip.id || ( 'chip-' + index );
    }

    function getSectionIdFromChip( chip ) {
        if ( chip.dataset.vanaSection ) return chip.dataset.vanaSection;
        if ( chip.dataset.target ) return chip.dataset.target;

        var href = chip.getAttribute( 'href' ) || '';
        if ( href.startsWith( '#' ) ) {
            return href.slice( 1 );
        }

        return null;
    }

    function getSections( chips ) {
        // Coleta unificada: data-vana-section + ids referenciados pelos chips.
        var byId = {};
        var sections = [];

        Array.prototype.slice.call( document.querySelectorAll( SECTION_SEL ) ).forEach( function ( sectionEl ) {
            var sectionId = sectionEl.id || sectionEl.dataset.vanaSection;
            if ( sectionId && ! byId[ sectionId ] ) {
                byId[ sectionId ] = true;
                sections.push( sectionEl );
            }
        } );

        chips.forEach( function ( chip ) {
            var id = getSectionIdFromChip( chip );
            if ( ! id || byId[ id ] ) return;
            var el = document.getElementById( id );
            if ( el ) {
                byId[ id ] = true;
                sections.push( el );
            }
        } );

        if ( sections.length === 0 ) {
            chips.forEach( function ( chip ) {
                var id = getSectionIdFromChip( chip );
                var el = id ? document.getElementById( id ) : null;
                if ( el && ! byId[ id ] ) {
                    byId[ id ] = true;
                    sections.push( el );
                }
            } );
        }

        return sections;
    }

    function scrollBarToChip( bar, chip ) {
        if ( ! bar || ! chip ) return;

        var chipLeft = chip.offsetLeft;
        var chipWidth = chip.offsetWidth;
        var barWidth = bar.offsetWidth;

        bar.scrollTo( {
            left: chipLeft - ( barWidth / 2 ) + ( chipWidth / 2 ),
            behavior: 'smooth',
        } );
    }

    // 3. Scroll suave para seção
    function scrollToSection( sectionId ) {
        if ( ! sectionId ) return;

        var target = document.getElementById( sectionId );
        if ( ! target ) return;

        // Offset herdado da implementação atual: header + chip bar + folga.
        var offset = 56 + 45 + 8;
        var top = target.getBoundingClientRect().top + window.pageYOffset - offset;

        window.scrollTo( { top: top, behavior: 'smooth' } );
    }

    // 4. Highlight do chip ativo
    function setActiveChip( chipId ) {
        var bar = getBar();
        var chips = getChips( bar );

        if ( chips.length === 0 || ! chipId ) return;

        chips.forEach( function ( chip, index ) {
            var id = getChipId( chip, index );
            var isActive = id === chipId;
            chip.classList.toggle( 'is-active', isActive );
            if ( isActive ) {
                chip.setAttribute( 'aria-current', 'true' );
            } else {
                chip.removeAttribute( 'aria-current' );
            }
        } );

        var activeChip = null;
        chips.forEach( function ( chip, index ) {
            if ( ! activeChip && getChipId( chip, index ) === chipId ) {
                activeChip = chip;
            }
        } );
        if ( activeChip ) {
            scrollBarToChip( bar, activeChip );
            activeChipId = chipId;
            emit( 'vana:chip:activated', {
                chipId: chipId,
                sectionId: getSectionIdFromChip( activeChip ),
            } );
        }
    }

    // 5. IntersectionObserver das seções
    function initObserver() {
        var bar = getBar();
        var chips = getChips( bar );
        var sections = getSections( chips );

        if ( chips.length === 0 || sections.length === 0 || ! window.IntersectionObserver ) {
            return;
        }

        var bySectionId = {};
        chips.forEach( function ( chip, index ) {
            var sectionId = getSectionIdFromChip( chip );
            if ( sectionId ) {
                bySectionId[ sectionId ] = getChipId( chip, index );
            }
        } );

        observer = new IntersectionObserver( function ( entries ) {
            entries.forEach( function ( entry ) {
                if ( ! entry.isIntersecting ) return;
                var sectionId = entry.target.id || entry.target.dataset.vanaSection || null;
                if ( ! sectionId ) return;
                var chipId = bySectionId[ sectionId ] || null;
                if ( chipId && chipId !== activeChipId ) {
                    setActiveChip( chipId );
                }
            } );
        }, {
            rootMargin: '-56px 0px -60% 0px',
            threshold: 0,
        } );

        sections.forEach( function ( section ) {
            observer.observe( section );
        } );
    }

    function bindChipClicks() {
        var bar = getBar();
        var chips = getChips( bar );
        if ( chips.length === 0 ) return;

        chips.forEach( function ( chip, index ) {
            chip.addEventListener( 'click', function ( e ) {
                e.preventDefault();
                var sectionId = getSectionIdFromChip( chip );
                if ( ! sectionId ) return;
                setActiveChip( getChipId( chip, index ) );
                scrollToSection( sectionId );
            } );
        } );
    }

    // 7. Init
    function init() {
        var bar = getBar();
        var chips = getChips( bar );
        if ( chips.length === 0 ) return;

        bindChipClicks();
        initObserver();

        emit( 'vana:chip:bar:ready', {
            chips: chips.map( function ( chip, index ) {
                return {
                    chipId: getChipId( chip, index ),
                    sectionId: getSectionIdFromChip( chip ),
                };
            } ),
        } );
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }
} )();
