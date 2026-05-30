// hero-selector.js - Версия 4.2

class HeroSelector {
    constructor(options) {
        this.modalId = options.modalId || 'heroSelectorModal';
        this.onConfirm = options.onConfirm || function(selectedIds) {};
        this.onCancel = options.onCancel || function() {};
        this.maxHeroes = options.maxHeroes || 5;
        this.selectedIds = options.selectedIds || [];
        this.allHeroes = options.allHeroes || [];
        this.heroesWithStats = [];
        this.modal = null;
        this.modalBody = null;
        this.currentSearchTerm = '';
        this.currentSortType = 'popularity';
        this.heroesLoaded = false;
        this.maxPopularity = 1;
    }

    init() {
        this.createModal();
        if (this.allHeroes.length > 0) {
            this.heroesWithStats = this.allHeroes;
            this.heroesLoaded = true;
            this.maxPopularity = Math.max(...this.allHeroes.map(h => h.usage_count || 0), 1);
        } else {
            this.loadHeroesWithStats();
        }
    }

    createModal() {
        if (document.getElementById(this.modalId)) {
            this.modal = document.getElementById(this.modalId);
            this.modalBody = document.getElementById(`${this.modalId}Body`);
            return;
        }

        const modalHTML = `
            <div class="modal fade" id="${this.modalId}" tabindex="-1" data-bs-backdrop="static">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-users"></i> 
                                Выбор героев (макс. ${this.maxHeroes})
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body" id="${this.modalId}Body">
                            <div class="text-center py-4">
                                <i class="fas fa-spinner fa-spin"></i> Загрузка...
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times"></i> Отмена
                            </button>
                            <button type="button" class="btn btn-primary" id="${this.modalId}ConfirmBtn">
                                <i class="fas fa-save"></i> Сохранить
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        $('body').append(modalHTML);
        
        this.modal = document.getElementById(this.modalId);
        this.modalBody = document.getElementById(`${this.modalId}Body`);
        
        $(`#${this.modalId}ConfirmBtn`).on('click', () => this.confirm());
    }

    loadHeroesWithStats() {
        $.ajax({
            url: 'ajax/HeroHandler.php?action=get_heroes_sorted',
            type: 'GET',
            dataType: 'json',
            success: (heroes) => {
                this.heroesWithStats = heroes;
                this.allHeroes = heroes;
                this.maxPopularity = Math.max(...heroes.map(h => h.usage_count || 0), 1);
                this.heroesLoaded = true;
                if (this.modal && $(this.modal).hasClass('show')) {
                    this.render();
                }
            },
            error: () => {
                console.error('Ошибка загрузки героев');
            }
        });
    }

    open(selectedIds = null) {
        if (selectedIds !== null) {
            this.selectedIds = [...selectedIds];
        }
        this.currentSearchTerm = '';
        
        if (!this.heroesLoaded && this.allHeroes.length === 0) {
            this.loadHeroesWithStats();
            if (this.modalBody) {
                $(this.modalBody).html('<div class="text-center py-4"><i class="fas fa-spinner fa-spin"></i> Загрузка героев...</div>');
            }
        } else if (this.allHeroes.length > 0 && !this.heroesLoaded) {
            this.heroesWithStats = this.allHeroes;
            this.heroesLoaded = true;
            this.maxPopularity = Math.max(...this.allHeroes.map(h => h.usage_count || 0), 1);
            this.render();
        } else {
            this.render();
        }
        
        $(this.modal).modal('show');
    }

    close() {
        $(this.modal).modal('hide');
    }

    clearSearch() {
        this.currentSearchTerm = '';
        const searchInput = $(`#${this.modalId}Search`);
        if (searchInput.length) {
            searchInput.val('');
        }
        this.filterAndSortItems();
    }

    filterAndSortItems() {
        const $container = $(`#${this.modalId}List`);
        const $items = $container.children('.hero-checkbox-item').toArray();
        
        let visibleItems = $items;
        if (this.currentSearchTerm) {
            const searchLower = this.currentSearchTerm.toLowerCase();
            visibleItems = $items.filter(item => {
                const name = $(item).data('hero-name');
                return name.startsWith(searchLower);
            });
        }
        
        if (this.currentSortType === 'name') {
            visibleItems.sort((a, b) => $(a).data('hero-name').localeCompare($(b).data('hero-name')));
        } else if (this.currentSortType === 'name_desc') {
            visibleItems.sort((a, b) => $(b).data('hero-name').localeCompare($(a).data('hero-name')));
        } else if (this.currentSortType === 'popularity') {
            visibleItems.sort((a, b) => ($(b).data('hero-popularity') || 0) - ($(a).data('hero-popularity') || 0));
        }
        
        $items.forEach(item => $(item).hide());
        visibleItems.forEach(item => $(item).show());
        
        if (visibleItems.length === 0) {
            if ($(`#${this.modalId}EmptyMessage`).length === 0) {
                $container.append(`
                    <div id="${this.modalId}EmptyMessage" class="text-center text-muted py-5">
                        <i class="fas fa-search fa-2x mb-2 d-block"></i>
                        <p>Ничего не найдено</p>
                        <button class="btn btn-sm btn-outline-primary" id="${this.modalId}ResetSearchBtn">
                            <i class="fas fa-undo-alt"></i> Показать всех
                        </button>
                    </div>
                `);
                $(`#${this.modalId}ResetSearchBtn`).off('click').on('click', () => this.clearSearch());
            }
        } else {
            $(`#${this.modalId}EmptyMessage`).remove();
        }
    }

    render() {
        if (!this.modalBody) return;
        
        const maxPopularity = this.maxPopularity;
        
        let heroesHtml = '';
        this.heroesWithStats.forEach(hero => {
            const checked = this.selectedIds.includes(hero.id);
            const usageCount = hero.usage_count || 0;
            const popularityPercent = maxPopularity > 0 ? Math.round((usageCount / maxPopularity) * 100) : 0;
            
            let stars = '';
            const starCount = maxPopularity > 0 ? Math.round((usageCount / maxPopularity) * 5) : 0;
            for (let i = 0; i < starCount; i++) {
                stars += '<i class="fas fa-star"></i>';
            }
            for (let i = starCount; i < 5; i++) {
                stars += '<i class="far fa-star"></i>';
            }
            
            heroesHtml += `
                <div class="hero-checkbox-item ${checked ? 'selected-hero' : ''}" 
                     data-hero-id="${hero.id}"
                     data-hero-name="${hero.name.toLowerCase()}"
                     data-hero-popularity="${usageCount}">
                    <input type="checkbox" value="${hero.id}" ${checked ? 'checked' : ''}>
                    <img src="${hero.image || ''}" onerror="this.style.display='none'">
                    <div style="flex:1;">
                        <div class="hero-name">${escapeHtml(hero.name)}</div>
                        <div class="hero-stats">
                            <span class="hero-stars">${stars}</span>
                            <span><i class="fas fa-chart-line"></i> ${usageCount}</span>
                            ${usageCount > 0 ? `<span>(${popularityPercent}%)</span>` : ''}
                        </div>
                    </div>
                </div>
            `;
        });
        
        const html = `
            <div class="hero-modal-header mb-3">
                <div class="row g-2">
                    <div class="col-10">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" id="${this.modalId}Search" class="form-control" placeholder="Поиск по имени...">
                        </div>
                    </div>
                    <div class="col-2">
                        <select id="${this.modalId}Sort" class="form-select">
                            <option value="popularity" ${this.currentSortType === 'popularity' ? 'selected' : ''}>Популярность</option>
                            <option value="name" ${this.currentSortType === 'name' ? 'selected' : ''}>Имя (А-Я)</option>
                            <option value="name_desc" ${this.currentSortType === 'name_desc' ? 'selected' : ''}>Имя (Я-А)</option>
                        </select>
                    </div>
                </div>
            </div>
            <div id="${this.modalId}List" class="hero-checkbox-list">
                ${heroesHtml}
            </div>
        `;
        
        $(this.modalBody).html(html);
        this.attachEvents();
        this.filterAndSortItems();
    }
    
    attachEvents() {
        const searchInput = $(`#${this.modalId}Search`);
        searchInput.off('keyup').on('keyup', (e) => {
            this.currentSearchTerm = e.target.value;
            this.filterAndSortItems();
        });
        
        $(`#${this.modalId}Sort`).off('change').on('change', (e) => {
            this.currentSortType = e.target.value;
            this.filterAndSortItems();
        });
        
        $(`#${this.modalId}List .hero-checkbox-item`).off('click').on('click', (e) => {
            if ($(e.target).is('input[type="checkbox"]')) return;
            
            const $item = $(e.currentTarget);
            const checkbox = $item.find('input[type="checkbox"]');
            const heroId = parseInt(checkbox.val());
            const newState = !checkbox.prop('checked');
            
            if (newState) {
                if (this.selectedIds.length >= this.maxHeroes) {
                    alert(`Не более ${this.maxHeroes} героев!`);
                    return;
                }
                this.selectedIds.push(heroId);
                checkbox.prop('checked', true);
                $item.addClass('selected-hero');
                this.currentSearchTerm = '';
                searchInput.val('');
                this.filterAndSortItems();
            } else {
                const index = this.selectedIds.indexOf(heroId);
                if (index !== -1) this.selectedIds.splice(index, 1);
                checkbox.prop('checked', false);
                $item.removeClass('selected-hero');
            }
        });
        
        $(`#${this.modalId}List input[type="checkbox"]`).off('click').on('click', (e) => {
            e.stopPropagation();
            const checkbox = $(e.currentTarget);
            const $item = checkbox.closest('.hero-checkbox-item');
            const heroId = parseInt(checkbox.val());
            const newState = checkbox.prop('checked');
            
            if (newState) {
                if (this.selectedIds.length >= this.maxHeroes) {
                    alert(`Не более ${this.maxHeroes} героев!`);
                    checkbox.prop('checked', false);
                    return;
                }
                this.selectedIds.push(heroId);
                $item.addClass('selected-hero');
                this.currentSearchTerm = '';
                searchInput.val('');
                this.filterAndSortItems();
            } else {
                const index = this.selectedIds.indexOf(heroId);
                if (index !== -1) this.selectedIds.splice(index, 1);
                checkbox.prop('checked', false);
                $item.removeClass('selected-hero');
            }
        });
    }
    
    confirm() {
        this.onConfirm([...this.selectedIds]);
        this.close();
    }
    
    setSelectedIds(ids) {
        this.selectedIds = [...ids];
    }
    
    destroy() {
        if (this.modal) {
            $(this.modal).modal('dispose');
            $(this.modal).remove();
        }
    }
}

window.HeroSelector = HeroSelector;