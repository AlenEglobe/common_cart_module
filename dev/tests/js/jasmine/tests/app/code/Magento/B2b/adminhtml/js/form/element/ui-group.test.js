/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
define([
    'jquery',
    'underscore',
    'Magento_B2b/js/form/element/ui-group'
], function ($, _, Select) {
    'use strict';

    describe('B2b/js/form/element/ui-group', function () {
        var select,
            event,
            options,
            params,
            elements;

        beforeEach(function () {
            var i;

            event = {
                target: {
                    value: 'opt',
                    attr: ''
                },

                /** @inheritdoc */
                stopPropagation: function () {
                    return true;
                },

                /** @inheritdoc */
                preventDefault: function () {
                    return true;
                },
                keyCode: ''
            };
            options = [{
                label: 'group-name',
                value: [{
                    label: 'option-name-one',
                    value: '1'
                },
                {
                    label: 'option-name-two',
                    value: '2'
                }]
            }];
            params = {
                dataScope: 'abstract',
                options: options,
                value: ['1'],
                multiple: true
            };
            elements = $([]);

            for (i = 0; i <= 3; i++) {
                elements.push($('<option />'));
            }

            select = new Select(params);
        });

        describe('setGroupParam method', function () {
            it('check options value', function () {
                select.options()[0] = {
                    label: 'General',
                    value: '1'
                };
                select.setGroupParam();
                expect(select.group).toBe(false);
            });
        });

        describe('setCacheOptions method', function () {
            it('check cache options', function () {
                var _options = _.clone(options);

                expect(JSON.parse(JSON.stringify(select.cacheOptionsGroup))).toEqual(_options);
            });

            it('check selected options', function () {
                expect(JSON.parse(JSON.stringify(select.selected()))).toEqual(['1']);
            });

            it('check cache options value', function () {
                expect(JSON.parse(JSON.stringify(select.cacheOptionsValue))).toEqual(['1', '2']);
            });

            it('check if renderSelectedOptions has been called', function () {
                spyOn(select, 'renderSelectedOptions');
                select.setCacheOptions();
                expect(select.renderSelectedOptions).toHaveBeenCalled();
            });

            it('check cache options value after setting group param', function () {
                spyOn(select, 'renderSelectedOptions');
                select.options()[0] = {
                    label: 'General',
                    value: '1'
                };
                select.setCacheOptions();
                expect(select.cacheOptionsValue[0]).toBeUndefined();

                select.setGroupParam();
                select.setCacheOptions();
                expect(select.cacheOptionsValue[0]).toBeDefined();
            });
        });

        describe('selectAll method', function () {
            it('check if all options are selected', function () {
                select.selectAll();
                expect(JSON.parse(JSON.stringify(select.selected()))).toEqual(['1', '2']);
            });
        });

        describe('deselectAll method', function () {
            it('check if all options are deselected', function () {
                select.deselectAll();
                expect(JSON.parse(JSON.stringify(select.selected()))).toEqual([]);
            });
        });

        describe('hasData method', function () {
            it('check availability selected options', function () {
                expect(select.hasData()).toBeTruthy();
            });

            it('check inaccessibility selected options', function () {
                select.deselectAll();
                expect(select.hasData()).toBeFalsy();
            });
        });

        describe('getSelectedQuantity method', function () {
            it('get selected quantity while one option is selected', function () {
                expect(select.getSelectedQuantity()).toBe(1);
            });

            it('get selected quantity while all option are deselected', function () {
                select.deselectAll();
                expect(select.getSelectedQuantity()).toBe(0);
            });
        });

        describe('getLabelSelectedQuantity method', function () {
            it('get label with selected quantity', function () {
                spyOn(select, 'getSelectedQuantity').and.returnValues(1);
                spyOn(select, 'getSelected').and.returnValues(2);
                expect(select.getLabelSelectedQuantity()).toBe('1 of 2 selected');
            });
        });

        describe('getOptionsQuantity method', function () {
            it('check for default', function () {
                expect(select.getOptionsQuantity()).toBe(2);
            });
        });

        describe('getFilteredOptionsQuantity method', function () {
            it('check while filtered options is empty', function () {
                expect(select.getFilteredOptionsQuantity()).toBe(0);
            });

            it('check after filtered option set', function () {
                var option = {
                    value: 1
                };

                select.filteredOptions().push(option);
                expect(select.getFilteredOptionsQuantity()).toBe(1);
            });
        });

        describe('getFilteredOptionsValues method', function () {
            it('check while filtered options is empty', function () {
                expect(JSON.parse(JSON.stringify(select.getFilteredOptionsValues()))).toEqual([]);
            });

            it('check after are filtered option set', function () {
                var option = {
                    value: 1
                };

                select.filteredOptions().push(option);
                expect(JSON.parse(JSON.stringify(select.getFilteredOptionsValues()))).toEqual([1]);
            });
        });

        describe('applyChange method', function () {
            it('check if setValue has been called', function () {
                spyOn(select, 'setValue');
                select.applyChange();
                expect(select.setValue).toHaveBeenCalled();
            });
        });

        describe('filterOptionsKeydown method', function () {
            beforeEach(function () {
                spyOn(select, 'setElements');
            });

            it('check options status', function () {
                select.filterOptionsKeydown(false, event);
                expect(select.getFilteredOptionsQuantity()).toBe(2);
                expect(select.convertedOptions().length).toBe(1);
                expect(select.isResult()).toBeTruthy();
            });

            it('check emersion event', function () {
                expect(select.filterOptionsKeydown()).toBeTruthy();
            });

            it('check if setQuantityItems has been called', function () {
                spyOn(select, 'setQuantityItems');
                select.filterOptionsKeydown(false, event);
                expect(select.setQuantityItems).toHaveBeenCalled();
            });

            it('check if setElements has been called', function () {
                select.filterOptionsKeydown(false, event);
                expect(select.setElements).toHaveBeenCalled();
            });
        });

        describe('setQuantityItems method', function () {
            it('check for default', function () {
                spyOn(select, 'getOptionsQuantity').and.returnValues(2);
                select.setQuantityItems();
                expect(select.quantitySearchItems()).toBe(2);
            });

            it('check after some given argument', function () {
                select.setQuantityItems('1');
                expect(select.quantitySearchItems()).toBe('1');
            });
        });

        describe('cancelChange method', function () {
            it('check after reset changes', function () {
                select.selected(['1','2']);
                expect(JSON.parse(JSON.stringify(select.selected()))).toEqual(['1','2']);
                select.cancelChange();
                expect(JSON.parse(JSON.stringify(select.selected()))).toEqual(['1']);
            });
        });

        describe('clearSearch method', function () {
            it('check after clear search params', function () {
                spyOn(select, 'setElements');
                select.filterOptionsKeydown(false, event);
                select.filterInputValue('opt');
                expect(select.isSearchActive()).toBeTruthy();
                select.clearSearch();
                expect(select.filterInputValue()).toEqual('');
                expect(select.isSearchActive()).toBeFalsy();
            });
        });

        describe('setValue method', function () {
            it('check after set value', function () {
                select.setValue(['2']);
                expect(JSON.parse(JSON.stringify(select.value()))).toEqual(['2']);
            });

            it('check if renderSelectedOptions has been called', function () {
                spyOn(select, 'renderSelectedOptions');
                select.setValue();
                expect(select.renderSelectedOptions).toHaveBeenCalled();
            });

            it('check if resetOptions has been called', function () {
                spyOn(select, 'resetOptions');
                select.setValue();
                expect(select.resetOptions).toHaveBeenCalled();
            });

            it('check if list is closed', function () {
                select.listVisible(true);
                select.setValue(['2']);
                expect(select.listVisible()).toBeFalsy();
            });
        });

        describe('isSelected method', function () {
            it('check if option is selected', function () {
                expect(select.isSelected('1')).toBeTruthy();
            });

            it('check if option is deselected', function () {
                expect(select.isSelected('2')).toBeFalsy();
            });
        });

        describe('setSelected method', function () {
            it('check after selected is set', function () {
                select.setSelected('2');
                expect(JSON.parse(JSON.stringify(select.selected()))).toEqual(['1','2']);
            });
        });

        describe('removeSelected method', function () {
            it('check after remove selected', function () {
                select.setSelected('2');
                select.removeSelected('2', false, event);
                expect(JSON.parse(JSON.stringify(select.selected()))).toEqual(['1']);
            });
        });

        describe('getSelected method', function () {
            it('get selected value from cache options', function () {
                expect(select.getSelected()[0].value).toBe('1');
            });

            it('get selected value from set options', function () {
                expect(select.getSelected(options[0].value)[0].value).toBe('1');
            });
        });

        describe('outerClick method', function () {
            it('check if list is closed for multiple', function () {
                select.listVisible(true);
                select.outerClick();
                expect(select.listVisible()).toBeFalsy();
            });

            it('check if list is closed for single', function () {
                select.listVisible(true);
                select.multiple = false;
                select.outerClick();
                expect(select.listVisible()).toBeFalsy();
            });
        });

        describe('resetHover method', function () {
            it('check after hover is reset', function () {
                select.elementOptions = $('<option />');
                select.setHoverToElement(1);
                select.resetHover();
                expect(select.direction).toBe(-1);
            });
        });

        describe('onFocusIn method', function () {
            it('check if focus', function () {
                select.onFocusIn();
                expect(select.hasFocus()).toBeTruthy();
            });
        });

        describe('onFocusOut method', function () {
            it('check if focus out', function () {
                select.onFocusOut();
                expect(select.hasFocus()).toBeFalsy();
            });
        });

        describe('toggleListVisible method', function () {
            it('check visible after opened', function () {
                spyOn(select, 'setElements');
                select.listVisible(true);
                select.toggleListVisible();
                expect(select.listVisible()).toBeFalsy();
            });

            it('check visible after closed', function () {
                spyOn(select, 'setElements');
                select.listVisible(false);
                select.toggleListVisible();
                expect(select.listVisible()).toBeTruthy();
            });
        });

        describe('setElements method', function () {
            it('check cached elements', function () {
                var option = $('<option />', {
                    attr: {
                        'data-role': 'option'
                    }
                });

                event.target = $('<optgroup />')[0];
                $(event.target).append(option);
                select.setElements(event);
                expect(select.element).toBeDefined();
                expect(select.elementOptions).toBeDefined();
            });
        });

        describe('resetOptions method', function () {
            it('check after options is reset', function () {
                select.elementOptions = $('<option />');
                select.filterInputValue('val');
                select.filteredOptions(['label']);
                select.temporaryValue = 1;
                select.setHoverToElement(1);
                select.isSearchActive(true);
                select.isResult(true);
                select.resetOptions();
                expect(select.filterInputValue()).toBe('');
                expect(JSON.parse(JSON.stringify(select.filteredOptions()))).toEqual([]);
                expect(select.temporaryValue).toBeFalsy();
                expect(select.direction).toBe(-1);
                expect(select.isSearchActive()).toBeFalsy();
                expect(select.isResult()).toBeFalsy();
            });
        });

        describe('isArrowKey method', function () {
            it('check if key is arrow', function () {
                expect(select.isArrowKey('pageDownKey')).toBeTruthy();
            });

            it('check if key is not arrow', function () {
                expect(select.isArrowKey('enterKey')).toBeFalsy();
            });
        });

        describe('keydownSwitcher method', function () {
            it('check emersion event', function () {
                expect(select.keydownSwitcher(false, event)).toBeTruthy();
            });

            it('check call from one of events', function () {
                event.keyCode = 'escapeKey';
                spyOn(select, 'escapeKeyHandler');
                select.keyDownHandlers()[event.keyCode]();
                expect(select.escapeKeyHandler).toHaveBeenCalled();
            });
        });

        describe('enterKeyHandler method', function () {
            beforeEach(function () {
                select.element = {
                    /** @inheritdoc */
                    trigger: function () {
                        return true;
                    }
                };
            });

            it('check visible after enter handler for single select', function () {
                select.multiple = false;
                select.listVisible(true);
                select.enterKeyHandler(false, event);
                expect(select.listVisible()).toBeFalsy();
            });

            it('check visible after enter handler for multiple select', function () {
                select.listVisible(true);
                select.enterKeyHandler(false, event);
                expect(select.listVisible()).toBeFalsy();
            });

            it('check value after enter handler for single select', function () {
                select.multiple = false;
                select.temporaryValue = '2';
                select.enterKeyHandler(false, event);
                expect(select.value()).toBe('2');
            });

            it('check value after enter handler for multiple select', function () {
                select.selected(['2']);
                select.enterKeyHandler(false, event);
                select.applyChange();
                expect(select.value()).toEqual(['2']);
            });
        });

        describe('escapeKeyHandler method', function () {
            beforeEach(function () {
                select.element = {
                    /** @inheritdoc */
                    trigger: function () {
                        return true;
                    }
                };
            });

            it('check visible after escape handler', function () {
                select.listVisible(true);
                select.escapeKeyHandler();
                expect(select.listVisible()).toBeFalsy();
            });

            it('check if cancelChange has been called for multiple', function () {
                spyOn(select, 'resetOptions');
                select.escapeKeyHandler();
                expect(select.resetOptions).toHaveBeenCalled();
            });
        });

        describe('setHoverToElement method', function () {
            beforeEach(function () {
                select.elementOptions = elements;
            });

            it('check hover class on a hovered element', function () {
                select.direction = 0;
                select.setHoverToElement(1);
                expect(select.elementOptions[select.direction].hasClass(select.hoverClass)).toBeTruthy();
            });
        });

        describe('pageUpKeyHandler method', function () {
            beforeEach(function () {
                select.elementOptions = elements;
            });

            it('check if setHoverToElement has been called', function () {
                spyOn(select, 'setHoverToElement');
                select.pageUpKeyHandler(false, event);
                expect(select.setHoverToElement).toHaveBeenCalled();
            });

            it('check correct direction', function () {
                select.direction = 3;
                select.pageUpKeyHandler(false, event);
                expect(select.direction).toBe(2);
            });
        });

        describe('pageDownKeyHandler method', function () {
            beforeEach(function () {
                select.elementOptions = elements;
            });

            it('check if setHoverToElement has been called', function () {
                spyOn(select, 'setHoverToElement');
                select.pageDownKeyHandler(false, event);
                expect(select.setHoverToElement).toHaveBeenCalled();
            });

            it('check correct direction', function () {
                select.direction = 1;
                select.pageDownKeyHandler(false, event);
                expect(select.direction).toBe(2);
            });
        });

        describe('spaceKeyHandler method', function () {
            beforeEach(function () {
                event.target = $('<element />', {
                    attr: {
                        'data-value': 2
                    }
                });
            });

            it('check selected after space handler', function () {
                select.spaceKeyHandler(false, event);
                expect(JSON.parse(JSON.stringify(select.selected()))).toEqual(['1', '2']);
            });
        });
    });
});
