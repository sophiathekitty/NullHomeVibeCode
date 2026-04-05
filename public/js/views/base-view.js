/**
 * Base class for all views.
 * Handles <template> cloning and container management.
 * Subclasses implement render(data).
 */
class BaseView {

    /**
     * @param {string} templateId - The id of the <template> element in the DOM.
     * @param {string} containerId - The id of the container element to render into.
     */
    constructor(templateId, containerId) {
        this.template  = document.getElementById(templateId);
        this.container = document.getElementById(containerId);
    }

    /**
     * Returns a deep clone of the template's content DocumentFragment.
     *
     * @returns {DocumentFragment} Cloned template content ready for modification.
     */
    cloneTemplate() {
        return this.template.content.cloneNode(true);
    }

    /**
     * Removes all child nodes from the container.
     *
     * @returns {void}
     */
    clear() {
        this.container.innerHTML = '';
    }

    /**
     * Renders data into the container. Must be overridden by subclasses.
     *
     * @param {Object|Array} data - Unwrapped data from a model method.
     * @returns {void}
     */
    render(data) {
        throw new Error('BaseView.render() must be implemented by subclass.');
    }
}

if (typeof module !== 'undefined') { module.exports = BaseView; }
