/**
 * BlogKit Advanced UI Components
 * 高级JavaScript组件库
 * 参考laydate设计模式
 */

(function(window, document, undefined) {
    'use strict';

    class DatePicker {
        static instances = [];
        
        static hideAll() {
            DatePicker.instances.forEach(instance => {
                if (instance.isVisible) {
                    instance.hide();
                }
            });
        }
        
        constructor(element, options = {}) {
            this.element = element;
            this.options = Object.assign({
                format: 'YYYY-MM-DD',
                minDate: null,
                maxDate: null,
                todayHighlight: true,
                showBottom: true,
                onChange: null,
                onChoose: null
            }, options);
            this.calendar = null;
            this.currentDate = null;
            this.isVisible = false;
            
            DatePicker.instances.push(this);
            
            this.init();
        }

        init() {
            const self = this;
            
            this.element.addEventListener('click', function(e) {
                e.stopPropagation();
                self.toggle();
            });

            this.element.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    self.validateAndApply();
                } else if (e.key === 'Escape') {
                    self.hide();
                }
            });

            this.element.addEventListener('blur', function() {
                self.validateAndApply();
            });

            document.addEventListener('click', function(e) {
                if (self.isVisible && self.calendar && !self.calendar.contains(e.target) && e.target !== self.element) {
                    self.hide();
                }
            });
        }
        
        validateAndApply() {
            const value = this.element.value.trim();
            if (!value) {
                this.currentDate = new Date();
                return;
            }
            
            const date = new Date(value);
            if (!isNaN(date.getTime())) {
                this.currentDate = date;
                this.element.value = date.toISOString().split('T')[0];
                if (this.options.onChange) {
                    this.options.onChange(this.element.value, date);
                }
            } else {
                this.element.value = this.currentDate.toISOString().split('T')[0];
            }
        }

        toggle() {
            if (this.isVisible) {
                this.hide();
            } else {
                this.show();
            }
        }

        show() {
            if (this.isVisible) return;
            
            DatePicker.hideAll();
            
            const dateStr = this.element.value;
            this.currentDate = dateStr ? new Date(dateStr) : new Date();
            
            if (this.calendar) {
                this.calendar.remove();
            }
            
            this.calendar = this.createCalendar();
            document.body.appendChild(this.calendar);
            
            const self = this;
            requestAnimationFrame(function() {
                self.positionCalendar();
            });
            
            this.scrollHandler = function() {
                if (self.isVisible && self.calendar) {
                    self.positionCalendar();
                }
            };
            window.addEventListener('scroll', this.scrollHandler, true);
            
            this.isVisible = true;
        }

        hide() {
            if (!this.isVisible) return;
            
            if (this.scrollHandler) {
                window.removeEventListener('scroll', this.scrollHandler, true);
                this.scrollHandler = null;
            }
            
            if (this.calendar) {
                this.calendar.remove();
                this.calendar = null;
            }
            this.isVisible = false;
        }

        positionCalendar() {
            if (!this.calendar) return;
            
            const rect = this.element.getBoundingClientRect();
            const viewportWidth = window.innerWidth;
            const viewportHeight = window.innerHeight;
            const calendarWidth = this.calendar.offsetWidth || 280;
            const calendarHeight = this.calendar.offsetHeight || 300;
            
            let left = rect.left;
            let top = rect.bottom + 8;
            
            if (left + calendarWidth > viewportWidth) {
                left = viewportWidth - calendarWidth - 10;
            }
            
            if (top + calendarHeight > viewportHeight) {
                top = rect.top - calendarHeight - 8;
            }
            
            this.calendar.style.left = left + 'px';
            this.calendar.style.top = top + 'px';
            this.calendar.style.position = 'fixed';
            this.calendar.style.zIndex = 9999;
        }

        createCalendar() {
            const container = document.createElement('div');
            container.className = 'blogkit-date-calendar';
            
            container.innerHTML = this.getCalendarHTML(this.currentDate);
            
            this.bindCalendarEvents(container);
            
            return container;
        }

        getCalendarHTML(date) {
            const year = date.getFullYear();
            const month = date.getMonth();
            const days = this.getDaysInMonth(date);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            let html = `
                <div class="blogkit-date-header">
                    <button class="blogkit-date-prev" data-month="-1">&lt;</button>
                    <button class="blogkit-date-year" data-type="year">${year}</button>
                    <button class="blogkit-date-month" data-type="month">${month + 1}月</button>
                    <button class="blogkit-date-next" data-month="+1">&gt;</button>
                </div>
                <div class="blogkit-date-content">
                    <table class="blogkit-date-table">
                        <thead>
                            <tr>
                                <th>日</th>
                                <th>一</th>
                                <th>二</th>
                                <th>三</th>
                                <th>四</th>
                                <th>五</th>
                                <th>六</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            for (let i = 0; i < days.length; i += 7) {
                html += '<tr>';
                for (let j = 0; j < 7; j++) {
                    const day = days[i + j];
                    if (day) {
                        const dayDate = day.dateObj;
                        const dayStr = dayDate.toISOString().split('T')[0];
                        let className = 'blogkit-date-day';
                        
                        if (day.otherMonth) className += ' blogkit-date-other';
                        if (dayDate.getTime() === today.getTime()) className += ' blogkit-date-today';
                        if (this.element.value === dayStr) className += ' blogkit-date-selected';
                        
                        html += `<td><span class="${className}" data-date="${dayStr}">${day.date}</span></td>`;
                    } else {
                        html += '<td></td>';
                    }
                }
                html += '</tr>';
            }
            
            html += `
                        </tbody>
                    </table>
                </div>
            `;
            
            if (this.options.showBottom) {
                html += `
                    <div class="blogkit-date-footer">
                        <button class="blogkit-date-btn blogkit-date-clear">清空</button>
                        <button class="blogkit-date-btn blogkit-date-today-btn">今天</button>
                        <button class="blogkit-date-btn blogkit-date-confirm">确定</button>
                    </div>
                `;
            }
            
            return html;
        }

        bindCalendarEvents(container) {
            const self = this;
            
            container.addEventListener('click', function(e) {
                e.stopPropagation();
                
                const target = e.target;
                
                if (target.classList.contains('blogkit-date-prev') || target.classList.contains('blogkit-date-next')) {
                    const delta = parseInt(target.getAttribute('data-month'));
                    self.changeMonth(delta);
                } else if (target.classList.contains('blogkit-date-year')) {
                    self.showYearPicker();
                } else if (target.classList.contains('blogkit-date-month')) {
                    self.showMonthPicker();
                } else if (target.classList.contains('blogkit-date-day')) {
                    const dateStr = target.getAttribute('data-date');
                    if (dateStr) {
                        self.selectDate(dateStr);
                    }
                } else if (target.classList.contains('blogkit-date-clear')) {
                    self.clear();
                } else if (target.classList.contains('blogkit-date-today-btn')) {
                    const today = new Date();
                    self.selectDate(today.toISOString().split('T')[0]);
                } else if (target.classList.contains('blogkit-date-confirm')) {
                    self.hide();
                }
            });
        }

        changeMonth(delta) {
            const newMonth = this.currentDate.getMonth() + delta;
            this.currentDate = new Date(this.currentDate.getFullYear(), newMonth, 1);
            this.updateCalendar();
        }

        showYearPicker() {
            const self = this;
            const currentYear = this.currentDate.getFullYear();
            const yearsPerPage = 24;
            let currentPage = Math.max(0, Math.floor((currentYear - 1970) / yearsPerPage));
            
            const renderYearPicker = function(page) {
                const startYear = 1970 + page * yearsPerPage;
                const endYear = startYear + yearsPerPage - 1;
                
                let html = `
                    <div class="blogkit-date-year-header">
                        <button class="blogkit-date-year-prev">‹</button>
                        <span>${startYear} - ${endYear}</span>
                        <button class="blogkit-date-year-next">›</button>
                    </div>
                    <div class="blogkit-date-year-grid">
                `;
                
                for (let y = startYear; y <= endYear; y++) {
                    const className = y === currentYear ? 'blogkit-date-year-item blogkit-date-year-active' : 'blogkit-date-year-item';
                    html += `<span class="${className}" data-year="${y}">${y}</span>`;
                }
                html += '</div>';
                
                yearContainer.innerHTML = html;
            };
            
            const yearContainer = document.createElement('div');
            yearContainer.className = 'blogkit-date-year-picker';
            
            renderYearPicker(currentPage);
            
            yearContainer.addEventListener('click', function(e) {
                const target = e.target;
                if (target.classList.contains('blogkit-date-year-item')) {
                    const year = parseInt(target.getAttribute('data-year'));
                    self.currentDate = new Date(year, self.currentDate.getMonth(), 1);
                    yearContainer.remove();
                    self.updateCalendar();
                } else if (target.classList.contains('blogkit-date-year-prev')) {
                    currentPage = Math.max(0, currentPage - 1);
                    renderYearPicker(currentPage);
                } else if (target.classList.contains('blogkit-date-year-next')) {
                    currentPage++;
                    renderYearPicker(currentPage);
                }
            });
            
            this.calendar.innerHTML = '';
            this.calendar.appendChild(yearContainer);
        }

        showMonthPicker() {
            const self = this;
            const months = ['1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月'];
            const currentMonth = this.currentDate.getMonth();
            
            const monthContainer = document.createElement('div');
            monthContainer.className = 'blogkit-date-month-picker';
            
            let html = '<div class="blogkit-date-month-header">选择月份</div>';
            html += '<div class="blogkit-date-month-grid">';
            months.forEach((m, i) => {
                const className = i === currentMonth ? 'blogkit-date-month-item blogkit-date-month-active' : 'blogkit-date-month-item';
                html += `<span class="${className}" data-month="${i}">${m}</span>`;
            });
            html += '</div>';
            
            monthContainer.innerHTML = html;
            
            monthContainer.addEventListener('click', function(e) {
                const target = e.target;
                if (target.classList.contains('blogkit-date-month-item')) {
                    const month = parseInt(target.getAttribute('data-month'));
                    self.currentDate = new Date(self.currentDate.getFullYear(), month, 1);
                    monthContainer.remove();
                    self.updateCalendar();
                }
            });
            
            this.calendar.innerHTML = '';
            this.calendar.appendChild(monthContainer);
        }

        updateCalendar() {
            if (!this.calendar) return;
            
            const newCalendar = this.createCalendar();
            this.calendar.replaceWith(newCalendar);
            this.calendar = newCalendar;
            this.positionCalendar();
        }

        selectDate(dateStr) {
            const date = new Date(dateStr);
            
            if (this.options.minDate) {
                const minDate = new Date(this.options.minDate);
                if (date < minDate) return;
            }
            
            if (this.options.maxDate) {
                const maxDate = new Date(this.options.maxDate);
                if (date > maxDate) return;
            }
            
            this.element.value = dateStr;
            
            if (this.options.onChange) {
                this.options.onChange(dateStr, date);
            }
            
            if (this.options.onChoose) {
                this.options.onChoose(dateStr, date);
            }
            
            this.hide();
        }

        clear() {
            this.element.value = '';
            if (this.options.onChange) {
                this.options.onChange('', null);
            }
            this.hide();
        }

        getDaysInMonth(date) {
            const year = date.getFullYear();
            const month = date.getMonth();
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);
            const days = [];

            const startPadding = firstDay.getDay();
            const prevMonthLastDay = new Date(year, month, 0).getDate();
            for (let i = startPadding - 1; i >= 0; i--) {
                const d = new Date(year, month - 1, prevMonthLastDay - i);
                days.push({ date: prevMonthLastDay - i, dateObj: d, otherMonth: true });
            }

            for (let i = 1; i <= lastDay.getDate(); i++) {
                const d = new Date(year, month, i);
                days.push({ date: i, dateObj: d, otherMonth: false });
            }

            const remaining = 42 - days.length;
            for (let i = 1; i <= remaining; i++) {
                const d = new Date(year, month + 1, i);
                days.push({ date: i, dateObj: d, otherMonth: true });
            }

            return days;
        }
    }

    class TimePicker {
        constructor(element, options = {}) {
            this.element = element;
            this.options = Object.assign({
                format: 'HH:mm:ss',
                showSeconds: true,
                onChange: null
            }, options);
            this.popup = null;
            this.isVisible = false;
            this.init();
        }

        init() {
            const self = this;
            this.element.readOnly = true;
            
            this.element.addEventListener('click', function(e) {
                e.stopPropagation();
                self.toggle();
            });

            document.addEventListener('click', function(e) {
                if (self.isVisible && self.popup && !self.popup.contains(e.target)) {
                    self.hide();
                }
            });
        }

        toggle() {
            if (this.isVisible) {
                this.hide();
            } else {
                this.show();
            }
        }

        show() {
            if (this.isVisible) return;
            
            if (this.popup) {
                this.popup.remove();
            }
            
            this.popup = this.createPopup();
            document.body.appendChild(this.popup);
            
            const rect = this.element.getBoundingClientRect();
            this.popup.style.left = rect.left + 'px';
            this.popup.style.top = rect.bottom + 8 + 'px';
            this.popup.style.position = 'fixed';
            this.popup.style.zIndex = 9999;
            
            this.isVisible = true;
        }

        hide() {
            if (!this.isVisible) return;
            
            if (this.popup) {
                this.popup.remove();
                this.popup = null;
            }
            this.isVisible = false;
        }

        createPopup() {
            const container = document.createElement('div');
            container.className = 'laytime-popup';
            
            const time = this.element.value || '00:00:00';
            const parts = time.split(':');
            const hours = parseInt(parts[0]) || 0;
            const minutes = parseInt(parts[1]) || 0;
            const seconds = this.options.showSeconds ? (parseInt(parts[2]) || 0) : 0;
            
            let html = `<div class="laytime-container">`;
            
            html += this.createTimeColumn('hours', hours, 0, 23);
            html += `<span class="laytime-separator">:</span>`;
            html += this.createTimeColumn('minutes', minutes, 0, 59);
            
            if (this.options.showSeconds) {
                html += `<span class="laytime-separator">:</span>`;
                html += this.createTimeColumn('seconds', seconds, 0, 59);
            }
            
            html += `</div>`;
            
            container.innerHTML = html;
            
            this.bindTimeEvents(container);
            
            return container;
        }

        createTimeColumn(type, value, min, max) {
            const options = [];
            for (let i = min; i <= max; i++) {
                const padded = i.toString().padStart(2, '0');
                const selected = i === value ? ' laytime-selected' : '';
                options.push(`<option value="${i}"${selected}>${padded}</option>`);
            }
            
            return `
                <select class="laytime-select laytime-${type}" data-type="${type}">
                    ${options.join('')}
                </select>
            `;
        }

        bindTimeEvents(container) {
            const self = this;
            const selects = container.querySelectorAll('select');
            
            selects.forEach(select => {
                select.addEventListener('change', function() {
                    self.updateValue();
                });
            });
            
            container.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }

        updateValue() {
            if (!this.popup) return;
            
            const hourSelect = this.popup.querySelector('.laytime-hours');
            const minuteSelect = this.popup.querySelector('.laytime-minutes');
            const secondSelect = this.popup.querySelector('.laytime-seconds');
            
            const hours = parseInt(hourSelect.value).toString().padStart(2, '0');
            const minutes = parseInt(minuteSelect.value).toString().padStart(2, '0');
            
            let timeStr = `${hours}:${minutes}`;
            if (this.options.showSeconds && secondSelect) {
                const seconds = parseInt(secondSelect.value).toString().padStart(2, '0');
                timeStr += `:${seconds}`;
            }
            
            this.element.value = timeStr;
            
            if (this.options.onChange) {
                this.options.onChange(timeStr);
            }
        }
    }

    class ColorPicker {
        constructor(element, options = {}) {
            this.element = element;
            this.options = Object.assign({
                presetColors: [
                    '#000000', '#ffffff', '#ff0000', '#ff6600', '#ffcc00',
                    '#33cc00', '#00ccff', '#0066ff', '#9900ff', '#ff00cc',
                    '#f5f5f5', '#e0e0e0', '#d0d0d0', '#b0b0b0', '#808080',
                    '#e74c3c', '#e67e22', '#f1c40f', '#2ecc71', '#3498db',
                    '#9b59b6', '#1abc9c', '#2c3e50', '#34495e', '#7f8c8d'
                ],
                onChange: null
            }, options);
            this.popup = null;
            this.isVisible = false;
            this.init();
        }

        init() {
            const self = this;
            this.element.addEventListener('click', function(e) {
                e.stopPropagation();
                self.toggle();
            });

            document.addEventListener('click', function(e) {
                if (self.isVisible && self.popup && !self.popup.contains(e.target)) {
                    self.hide();
                }
            });
            
            if (this.element.value) {
                this.element.style.backgroundColor = this.element.value;
            }
        }

        toggle() {
            if (this.isVisible) {
                this.hide();
            } else {
                this.show();
            }
        }

        show() {
            if (this.isVisible) return;
            
            if (this.popup) {
                this.popup.remove();
            }
            
            this.popup = this.createPopup();
            document.body.appendChild(this.popup);
            
            const rect = this.element.getBoundingClientRect();
            this.popup.style.left = rect.left + 'px';
            this.popup.style.top = rect.bottom + 8 + 'px';
            this.popup.style.position = 'fixed';
            this.popup.style.zIndex = 9999;
            
            this.isVisible = true;
        }

        hide() {
            if (!this.isVisible) return;
            
            if (this.popup) {
                this.popup.remove();
                this.popup = null;
            }
            this.isVisible = false;
        }

        createPopup() {
            const container = document.createElement('div');
            container.className = 'laycolor-popup';
            
            let html = '<div class="laycolor-colors">';
            this.options.presetColors.forEach(color => {
                const selected = this.element.value === color ? ' laycolor-selected' : '';
                html += `<span class="laycolor-color${selected}" style="background-color: ${color}" data-color="${color}"></span>`;
            });
            html += '</div>';
            
            html += `
                <div class="laycolor-input">
                    <input type="text" class="laycolor-hex" value="${this.element.value || ''}" placeholder="#000000">
                </div>
            `;
            
            container.innerHTML = html;
            
            this.bindColorEvents(container);
            
            return container;
        }

        bindColorEvents(container) {
            const self = this;
            
            const colors = container.querySelectorAll('.laycolor-color');
            colors.forEach(color => {
                color.addEventListener('click', function() {
                    const colorValue = this.getAttribute('data-color');
                    self.selectColor(colorValue);
                });
            });
            
            const hexInput = container.querySelector('.laycolor-hex');
            hexInput.addEventListener('change', function() {
                const colorValue = this.value;
                if (/^#[0-9A-Fa-f]{6}$/.test(colorValue)) {
                    self.selectColor(colorValue);
                }
            });
            
            hexInput.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }

        selectColor(color) {
            this.element.value = color;
            this.element.style.backgroundColor = color;
            
            if (this.options.onChange) {
                this.options.onChange(color);
            }
            
            this.hide();
        }
    }

    class AutoComplete {
        constructor(element, options = {}) {
            this.element = element;
            this.options = Object.assign({
                url: '',
                data: [],
                minLength: 2,
                delay: 300,
                placeholder: '请输入搜索内容',
                onChange: null,
                onSelect: null
            }, options);
            this.dropdown = null;
            this.timer = null;
            this.isVisible = false;
            this.init();
        }

        init() {
            const self = this;
            
            if (this.options.placeholder) {
                this.element.placeholder = this.options.placeholder;
            }
            
            this.element.addEventListener('input', function() {
                const value = this.value.trim();
                
                if (self.timer) {
                    clearTimeout(self.timer);
                }
                
                if (value.length >= self.options.minLength) {
                    self.timer = setTimeout(function() {
                        self.search(value);
                    }, self.options.delay);
                } else {
                    self.hide();
                }
            });
            
            document.addEventListener('click', function(e) {
                if (self.isVisible && self.dropdown && !self.dropdown.contains(e.target)) {
                    self.hide();
                }
            });
        }

        search(value) {
            const self = this;
            
            if (this.options.data.length > 0) {
                const filtered = this.options.data.filter(item => {
                    const text = typeof item === 'object' ? item.text : item;
                    return text.toLowerCase().includes(value.toLowerCase());
                });
                this.showResults(filtered);
            } else if (this.options.url) {
                fetch(this.options.url + '?q=' + encodeURIComponent(value))
                    .then(response => response.json())
                    .then(data => {
                        self.showResults(data);
                    })
                    .catch(() => {
                        self.hide();
                    });
            }
        }

        showResults(results) {
            if (!results || results.length === 0) {
                this.hide();
                return;
            }
            
            if (this.dropdown) {
                this.dropdown.remove();
            }
            
            const container = document.createElement('div');
            container.className = 'laysearch-dropdown';
            
            let html = '<ul class="laysearch-list">';
            results.forEach((item, index) => {
                const text = typeof item === 'object' ? item.text : item;
                const value = typeof item === 'object' ? item.value : item;
                html += `<li class="laysearch-item" data-value="${value}" data-index="${index}">${text}</li>`;
            });
            html += '</ul>';
            
            container.innerHTML = html;
            document.body.appendChild(container);
            
            const rect = this.element.getBoundingClientRect();
            container.style.left = rect.left + 'px';
            container.style.top = rect.bottom + 2 + 'px';
            container.style.width = rect.width + 'px';
            container.style.position = 'fixed';
            container.style.zIndex = 9999;
            
            this.dropdown = container;
            this.isVisible = true;
            
            this.bindResultEvents(container);
        }

        bindResultEvents(container) {
            const self = this;
            const items = container.querySelectorAll('.laysearch-item');
            
            items.forEach(item => {
                item.addEventListener('click', function() {
                    const value = this.getAttribute('data-value');
                    const text = this.textContent;
                    
                    self.element.value = text;
                    
                    if (self.options.onSelect) {
                        self.options.onSelect(value, text);
                    }
                    
                    if (self.options.onChange) {
                        self.options.onChange(value, text);
                    }
                    
                    self.hide();
                });
            });
        }

        hide() {
            if (!this.isVisible) return;
            
            if (this.dropdown) {
                this.dropdown.remove();
                this.dropdown = null;
            }
            this.isVisible = false;
        }
    }

    class Tooltip {
        constructor(element, options = {}) {
            this.element = element;
            this.options = Object.assign({
                content: '',
                position: 'top',
                trigger: 'hover',
                delay: 300,
                theme: 'default'
            }, options);
            this.tip = null;
            this.timer = null;
            this.init();
        }

        init() {
            const self = this;
            
            if (this.options.trigger === 'hover') {
                this.element.addEventListener('mouseenter', function() {
                    self.timer = setTimeout(() => {
                        self.show();
                    }, self.options.delay);
                });
                
                this.element.addEventListener('mouseleave', function() {
                    if (self.timer) {
                        clearTimeout(self.timer);
                    }
                    self.hide();
                });
            } else if (this.options.trigger === 'click') {
                this.element.addEventListener('click', function(e) {
                    e.stopPropagation();
                    self.toggle();
                });
                
                document.addEventListener('click', function() {
                    self.hide();
                });
            }
        }

        toggle() {
            if (this.tip) {
                this.hide();
            } else {
                this.show();
            }
        }

        show() {
            if (this.tip) return;
            
            const container = document.createElement('div');
            container.className = `laytooltip laytooltip-${this.options.position} laytooltip-${this.options.theme}`;
            container.innerHTML = this.options.content;
            
            document.body.appendChild(container);
            
            const rect = this.element.getBoundingClientRect();
            this.positionTooltip(container, rect);
            
            this.tip = container;
        }

        hide() {
            if (!this.tip) return;
            
            this.tip.remove();
            this.tip = null;
        }

        positionTooltip(tip, rect) {
            const tipWidth = tip.offsetWidth;
            const tipHeight = tip.offsetHeight;
            const viewportWidth = window.innerWidth;
            
            let left, top;
            
            switch (this.options.position) {
                case 'top':
                    left = rect.left + (rect.width - tipWidth) / 2;
                    top = rect.top - tipHeight - 8;
                    break;
                case 'bottom':
                    left = rect.left + (rect.width - tipWidth) / 2;
                    top = rect.bottom + 8;
                    break;
                case 'left':
                    left = rect.left - tipWidth - 8;
                    top = rect.top + (rect.height - tipHeight) / 2;
                    break;
                case 'right':
                    left = rect.right + 8;
                    top = rect.top + (rect.height - tipHeight) / 2;
                    break;
            }
            
            if (left < 0) left = 0;
            if (left + tipWidth > viewportWidth) left = viewportWidth - tipWidth;
            
            tip.style.left = left + 'px';
            tip.style.top = top + 'px';
            tip.style.position = 'fixed';
            tip.style.zIndex = 9999;
        }
    }

    class Popover {
        constructor(element, options = {}) {
            this.element = element;
            this.options = Object.assign({
                title: '',
                content: '',
                position: 'top',
                trigger: 'click',
                theme: 'default'
            }, options);
            this.popover = null;
            this.init();
        }

        init() {
            const self = this;
            
            if (this.options.trigger === 'click') {
                this.element.addEventListener('click', function(e) {
                    e.stopPropagation();
                    self.toggle();
                });
                
                document.addEventListener('click', function() {
                    self.hide();
                });
            }
        }

        toggle() {
            if (this.popover) {
                this.hide();
            } else {
                this.show();
            }
        }

        show() {
            if (this.popover) return;
            
            const container = document.createElement('div');
            container.className = `laypopover laypopover-${this.options.position} laypopover-${this.options.theme}`;
            
            let html = '';
            if (this.options.title) {
                html += `<div class="laypopover-title">${this.options.title}</div>`;
            }
            html += `<div class="laypopover-content">${this.options.content}</div>`;
            
            container.innerHTML = html;
            document.body.appendChild(container);
            
            const rect = this.element.getBoundingClientRect();
            this.positionPopover(container, rect);
            
            this.popover = container;
        }

        hide() {
            if (!this.popover) return;
            
            this.popover.remove();
            this.popover = null;
        }

        positionPopover(popover, rect) {
            const popoverWidth = popover.offsetWidth;
            const popoverHeight = popover.offsetHeight;
            const viewportWidth = window.innerWidth;
            
            let left, top;
            
            switch (this.options.position) {
                case 'top':
                    left = rect.left + (rect.width - popoverWidth) / 2;
                    top = rect.top - popoverHeight - 8;
                    break;
                case 'bottom':
                    left = rect.left + (rect.width - popoverWidth) / 2;
                    top = rect.bottom + 8;
                    break;
                case 'left':
                    left = rect.left - popoverWidth - 8;
                    top = rect.top + (rect.height - popoverHeight) / 2;
                    break;
                case 'right':
                    left = rect.right + 8;
                    top = rect.top + (rect.height - popoverHeight) / 2;
                    break;
            }
            
            if (left < 0) left = 0;
            if (left + popoverWidth > viewportWidth) left = viewportWidth - popoverWidth;
            
            popover.style.left = left + 'px';
            popover.style.top = top + 'px';
            popover.style.position = 'fixed';
            popover.style.zIndex = 9999;
        }
    }

    class Slider {
        constructor(element, options = {}) {
            this.element = element;
            this.options = Object.assign({
                min: 0,
                max: 100,
                value: 50,
                step: 1,
                onChange: null,
                onComplete: null
            }, options);
            this.track = null;
            this.thumb = null;
            this.isDragging = false;
            this.init();
        }

        init() {
            this.render();
            this.bindEvents();
        }

        render() {
            const percentage = ((this.options.value - this.options.min) / (this.options.max - this.options.min)) * 100;
            
            this.element.innerHTML = `
                <div class="laytrack">
                    <div class="laytrack-fill" style="width: ${percentage}%"></div>
                    <div class="laythumb" style="left: ${percentage}%"></div>
                </div>
                <div class="layvalue">${this.options.value}</div>
            `;
            
            this.track = this.element.querySelector('.laytrack');
            this.thumb = this.element.querySelector('.laythumb');
        }

        bindEvents() {
            const self = this;
            
            this.track.addEventListener('click', function(e) {
                const rect = this.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const percentage = x / rect.width;
                self.setValue(percentage);
            });
            
            this.thumb.addEventListener('mousedown', function(e) {
                e.preventDefault();
                self.isDragging = true;
                
                document.addEventListener('mousemove', self.onDrag);
                document.addEventListener('mouseup', self.onDragEnd);
            });
            
            this.onDrag = function(e) {
                if (!self.isDragging) return;
                
                const rect = self.track.getBoundingClientRect();
                const x = Math.max(0, Math.min(e.clientX - rect.left, rect.width));
                const percentage = x / rect.width;
                self.setValue(percentage);
            };
            
            this.onDragEnd = function() {
                self.isDragging = false;
                document.removeEventListener('mousemove', self.onDrag);
                document.removeEventListener('mouseup', self.onDragEnd);
                
                if (self.options.onComplete) {
                    self.options.onComplete(self.options.value);
                }
            };
        }

        setValue(percentage) {
            const rawValue = this.options.min + percentage * (this.options.max - this.options.min);
            const steppedValue = Math.round(rawValue / this.options.step) * this.options.step;
            const clampedValue = Math.max(this.options.min, Math.min(this.options.max, steppedValue));
            
            this.options.value = clampedValue;
            
            const displayPercentage = ((clampedValue - this.options.min) / (this.options.max - this.options.min)) * 100;
            
            this.element.querySelector('.laytrack-fill').style.width = displayPercentage + '%';
            this.element.querySelector('.laythumb').style.left = displayPercentage + '%';
            this.element.querySelector('.layvalue').textContent = clampedValue;
            
            if (this.options.onChange) {
                this.options.onChange(clampedValue);
            }
        }
    }

    class Rating {
        constructor(element, options = {}) {
            this.element = element;
            this.options = Object.assign({
                max: 5,
                value: 0,
                readonly: false,
                theme: 'star',
                onChange: null
            }, options);
            this.init();
        }

        init() {
            this.render();
            if (!this.options.readonly) {
                this.bindEvents();
            }
        }

        render() {
            let html = '';
            for (let i = 1; i <= this.options.max; i++) {
                const filled = i <= this.options.value ? ' layrating-filled' : '';
                html += `<span class="layrating-item${filled}" data-value="${i}">&#9733;</span>`;
            }
            this.element.innerHTML = html;
        }

        bindEvents() {
            const self = this;
            const items = this.element.querySelectorAll('.layrating-item');
            
            items.forEach(item => {
                item.addEventListener('mouseenter', function() {
                    const value = parseInt(this.getAttribute('data-value'));
                    self.highlight(value);
                });
                
                item.addEventListener('mouseleave', function() {
                    self.highlight(self.options.value);
                });
                
                item.addEventListener('click', function() {
                    const value = parseInt(this.getAttribute('data-value'));
                    self.setValue(value);
                });
            });
        }

        highlight(value) {
            const items = this.element.querySelectorAll('.layrating-item');
            items.forEach((item, index) => {
                if (index + 1 <= value) {
                    item.classList.add('layrating-filled');
                } else {
                    item.classList.remove('layrating-filled');
                }
            });
        }

        setValue(value) {
            this.options.value = value;
            this.highlight(value);
            
            if (this.options.onChange) {
                this.options.onChange(value);
            }
        }
    }

    class TagInput {
        constructor(element, options = {}) {
            this.element = element;
            this.options = Object.assign({
                tags: [],
                placeholder: '输入标签后按回车',
                maxTags: 10,
                allowDuplicates: false,
                onChange: null
            }, options);
            this.container = null;
            this.input = null;
            this.init();
        }

        init() {
            this.render();
            this.bindEvents();
        }

        render() {
            this.element.style.display = 'none';
            
            this.container = document.createElement('div');
            this.container.className = 'laytag-container';
            
            let html = '<div class="laytag-list">';
            this.options.tags.forEach(tag => {
                html += this.createTag(tag);
            });
            html += '</div>';
            
            html += `<input type="text" class="laytag-input" placeholder="${this.options.placeholder}">`;
            
            this.container.innerHTML = html;
            this.element.parentNode.insertBefore(this.container, this.element.nextSibling);
            
            this.input = this.container.querySelector('.laytag-input');
        }

        createTag(tag) {
            return `<span class="laytag-tag" data-tag="${tag}">${tag}<span class="laytag-remove">×</span></span>`;
        }

        bindEvents() {
            const self = this;
            
            this.input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const value = this.value.trim();
                    if (value) {
                        self.addTag(value);
                        this.value = '';
                    }
                } else if (e.key === 'Backspace' && !this.value) {
                    const tags = self.container.querySelectorAll('.laytag-tag');
                    if (tags.length > 0) {
                        const lastTag = tags[tags.length - 1];
                        self.removeTag(lastTag.getAttribute('data-tag'));
                    }
                }
            });
            
            this.container.addEventListener('click', function(e) {
                const target = e.target;
                if (target.classList.contains('laytag-remove')) {
                    const tagElement = target.parentElement;
                    const tag = tagElement.getAttribute('data-tag');
                    self.removeTag(tag);
                }
            });
        }

        addTag(tag) {
            if (!this.options.allowDuplicates && this.options.tags.includes(tag)) {
                return;
            }
            
            if (this.options.tags.length >= this.options.maxTags) {
                return;
            }
            
            this.options.tags.push(tag);
            const tagElement = document.createElement('span');
            tagElement.className = 'laytag-tag';
            tagElement.setAttribute('data-tag', tag);
            tagElement.innerHTML = `${tag}<span class="laytag-remove">×</span>`;
            
            this.container.querySelector('.laytag-list').appendChild(tagElement);
            this.updateHiddenInput();
            
            if (this.options.onChange) {
                this.options.onChange(this.options.tags);
            }
        }

        removeTag(tag) {
            const index = this.options.tags.indexOf(tag);
            if (index > -1) {
                this.options.tags.splice(index, 1);
            }
            
            const tagElement = this.container.querySelector(`.laytag-tag[data-tag="${tag}"]`);
            if (tagElement) {
                tagElement.remove();
            }
            
            this.updateHiddenInput();
            
            if (this.options.onChange) {
                this.options.onChange(this.options.tags);
            }
        }

        updateHiddenInput() {
            this.element.value = JSON.stringify(this.options.tags);
        }
    }

    class FileUpload {
        constructor(element, options = {}) {
            this.element = element;
            this.options = Object.assign({
                url: '',
                accept: '*',
                multiple: false,
                maxSize: 5 * 1024 * 1024,
                onSelect: null,
                onProgress: null,
                onComplete: null,
                onError: null
            }, options);
            this.init();
        }

        init() {
            this.render();
            this.bindEvents();
        }

        render() {
            const self = this;
            
            this.element.style.display = 'none';
            
            const container = document.createElement('div');
            container.className = 'layupload-container';
            
            container.innerHTML = `
                <div class="layupload-drop">
                    <div class="layupload-icon">📁</div>
                    <div class="layupload-text">点击或拖拽上传文件</div>
                    <div class="layupload-hint">支持 ${this.formatSize(this.options.maxSize)} 以内的文件</div>
                </div>
                <div class="layupload-list"></div>
            `;
            
            this.element.parentNode.insertBefore(container, this.element.nextSibling);
            
            this.dropZone = container.querySelector('.layupload-drop');
            this.listContainer = container.querySelector('.layupload-list');
            
            this.dropZone.addEventListener('click', function() {
                self.element.click();
            });
            
            this.dropZone.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('layupload-dragover');
            });
            
            this.dropZone.addEventListener('dragleave', function() {
                this.classList.remove('layupload-dragover');
            });
            
            this.dropZone.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('layupload-dragover');
                const files = Array.from(e.dataTransfer.files);
                self.handleFiles(files);
            });
        }

        bindEvents() {
            const self = this;
            
            this.element.addEventListener('change', function() {
                const files = Array.from(this.files);
                self.handleFiles(files);
                this.value = '';
            });
        }

        handleFiles(files) {
            const self = this;
            
            files.forEach(file => {
                if (file.size > this.options.maxSize) {
                    if (this.options.onError) {
                        this.options.onError(file.name, '文件大小超过限制');
                    }
                    return;
                }
                
                if (this.options.accept !== '*' && !file.type.match(this.options.accept)) {
                    if (this.options.onError) {
                        this.options.onError(file.name, '文件类型不允许');
                    }
                    return;
                }
                
                if (this.options.onSelect) {
                    this.options.onSelect(file);
                }
                
                this.uploadFile(file);
            });
        }

        uploadFile(file) {
            const self = this;
            const formData = new FormData();
            formData.append('file', file);
            
            const xhr = new XMLHttpRequest();
            
            const item = this.createUploadItem(file);
            
            xhr.upload.addEventListener('progress', function(e) {
                const percentage = (e.loaded / e.total) * 100;
                self.updateProgress(item, percentage);
                
                if (self.options.onProgress) {
                    self.options.onProgress(file.name, percentage);
                }
            });
            
            xhr.addEventListener('load', function() {
                if (xhr.status === 200) {
                    item.classList.add('layupload-success');
                    if (self.options.onComplete) {
                        self.options.onComplete(file.name, xhr.responseText);
                    }
                } else {
                    item.classList.add('layupload-error');
                    if (self.options.onError) {
                        self.options.onError(file.name, xhr.statusText);
                    }
                }
            });
            
            xhr.open('POST', this.options.url);
            xhr.send(formData);
        }

        createUploadItem(file) {
            const item = document.createElement('div');
            item.className = 'layupload-item';
            item.innerHTML = `
                <div class="layupload-file-info">
                    <span class="layupload-file-name">${file.name}</span>
                    <span class="layupload-file-size">${this.formatSize(file.size)}</span>
                </div>
                <div class="layupload-progress">
                    <div class="layupload-progress-bar"></div>
                </div>
                <div class="layupload-status"></div>
            `;
            
            this.listContainer.appendChild(item);
            return item;
        }

        updateProgress(item, percentage) {
            const bar = item.querySelector('.layupload-progress-bar');
            bar.style.width = percentage + '%';
        }

        formatSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        }
    }

    window.BlogKitAdvanced = {
        DatePicker,
        TimePicker,
        ColorPicker,
        AutoComplete,
        Tooltip,
        Popover,
        Slider,
        Rating,
        TagInput,
        FileUpload
    };

})(window, document);