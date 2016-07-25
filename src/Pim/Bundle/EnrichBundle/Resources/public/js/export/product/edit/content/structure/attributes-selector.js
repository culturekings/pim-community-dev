'use strict';
/**
 * Attribute selector
 *
 * @author    Julien Sanchez <julien@akeneo.com>
 * @copyright 2016 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
define(
    [
        'jquery',
        'underscore',
        'oro/translator',
        'backbone',
        'pim/i18n',
        'pim/user-context',
        'pim/fetcher-registry',
        'text!pim/template/export/product/edit/content/structure/attributes-selector',
        'text!pim/template/export/product/edit/content/structure/attribute-list'
    ],
    function (
        $,
        _,
        __,
        Backbone,
        i18n,
        userContext,
        fetcherRegistry,
        template,
        attributeListTemplate
    ) {
        return Backbone.View.extend({
            events: {
                'click .attribute-groups li': 'changeAttributeGroup',
                'keyup .search-field': 'updateSearch',
                'click .clear': 'clear',
            },
            search: '',
            curentFetchId: 0,
            attributeListPage: 1,
            isFetching: false,
            selected: [],
            currentGroup: null,
            template: _.template(template),
            attributeListTemplate: _.template(attributeListTemplate),

            initialize: function () {
                this.listenTo(this, 'selected:update:after', function (selected) {
                    this.$('.empty-message')
                        .addClass(0 === selected.length ? 'empty' : '')
                        .removeClass(0 === selected.length ? '' : 'empty')
                });
            },

            /**
             * {@inheritdoc}
             */
            render: function () {
                $.when(
                    fetcherRegistry.getFetcher('attribute-group').fetchAll(),
                    fetcherRegistry.getFetcher('attribute').fetchByIdentifiers(this.getSelected())
                ).then(function (attributeGroups, selectedAttributes) {
                    var scrollPositions = {};
                    _.each(this.$('[data-scroll-container]'), function (element) {
                        scrollPositions[element.dataset.scrollContainer] = element.scrollTop;
                    });
                    this.attributeListPage = 1;
                    this.isFetching        = false;

                    var attributeCount = null === this.currentGroup ?
                        _.reduce(attributeGroups, function (count, attributeGroup) {
                            return count + attributeGroup.attributes.length;
                        }, 0) :
                        _.findWhere(attributeGroups, {code: this.currentGroup}).attributes.length

                    this.$el.empty().append(this.template({
                        __: __,
                        i18n: i18n,
                        userContext: userContext,
                        attributeGroups: attributeGroups,
                        attributeCount: attributeCount,
                        currentGroup: this.currentGroup,
                        selectedAttributes: selectedAttributes
                    }));

                    this.initializeSortable();

                    this.updateAttributeList().then(function () {
                        _.each(scrollPositions, function (scrollPosition, key) {
                            this.$('[data-scroll-container="' + key + '"]').scrollTop(scrollPosition);
                        }.bind(this));
                    }.bind(this));

                    this.$('.attributes div').on('scroll', this.updateAttributeList.bind(this));
                }.bind(this));
            },

            /**
             * Set currently selected attributes
             *
             * @param {array} selected
             */
            setSelected: function (selected) {
                this.selected = selected;

                this.trigger('selected:update:after', this.selected);
            },

            /**
             * Get currently selected attributes
             *
             * @return {array}
             */
            getSelected: function () {
                return this.selected;
            },
            changeAttributeGroup: function (event) {
                var newGroup = event.currentTarget.dataset.attributeGroupCode;
                newGroup = '' !== newGroup ? newGroup : null;
                this.search = '';

                this.currentGroup = newGroup;

                this.render();
            },
            updateSearch: function () {
                this.search            = this.$('.search-field').val();
                this.attributeListPage = 1;
                this.isFetching        = false;
                this.$('.attributes ul').empty();
                this.updateAttributeList();
            },
            clear: function () {
                this.setSelected([]);
                this.search = '';

                this.render();
            },
            updateAttributeList: function () {
                var attributeContainer = this.$('.attributes > div');
                var attributeList = attributeContainer.children('ul');

                var needFetching = 0 > (
                    attributeList.height() - attributeContainer.scrollTop() - 2 * attributeContainer.height()
                );

                if (needFetching && !this.isFetching) {
                    this.curentFetchId++;
                    var fetchId = this.curentFetchId + 0;
                    var searchOptions = {
                        search: this.search,
                        options: {
                            excluded_identifiers: this.getSelected(),
                            page: this.attributeListPage,
                            limit: 20
                        }
                    };

                    if (null !== this.currentGroup) {
                        searchOptions.options['attribute_groups'] = [this.currentGroup];
                    }

                    this.isFetching = true;

                    return fetcherRegistry
                        .getFetcher('attribute')
                        .search(searchOptions)
                        .then(function (attributes) {
                            attributes = _.filter(attributes, function (attribute) {
                                return attribute.type !== 'pim_catalog_identifier';
                            });

                            if (fetchId !== this.curentFetchId) {
                                return;
                            }
                            attributeContainer.children('ul').append(this.attributeListTemplate({
                                    attributes: attributes,
                                    i18n: i18n,
                                    userContext: userContext
                                })
                            );

                            if (0 !== attributes.length) {
                                this.attributeListPage++;
                                this.isFetching = false;
                            }
                        }.bind(this));
                }
            },
            initializeSortable: function () {
                this.$('.attributes ul, .selected-attributes ul').sortable({
                    connectWith: '.attributes ul, .selected-attributes ul',
                    containment: this.$el,
                    tolerance: 'pointer',
                    cursor: 'move',
                    receive: function (event, ui) {
                        var attributeCode = ui.item.data('attributeCode')
                        var selected = this.getSelected();

                        if (ui.sender.parents('div').hasClass('attributes')) {
                            selected = _.union(selected, [attributeCode]);
                        }
                        if (ui.sender.parents('div').hasClass('selected-attributes')) {
                            selected = _.without(selected, attributeCode);
                        }

                        this.setSelected(selected);
                    }.bind(this)
                }).disableSelection();
            }
        });
    }
);
