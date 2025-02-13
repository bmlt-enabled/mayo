/*
 * ATTENTION: The "eval" devtool has been used (maybe by default in mode: "development").
 * This devtool is neither made for production nor for readable output files.
 * It uses "eval()" calls to create a separate source file in the browser devtools.
 * If you are trying to read the output file, select a different devtool (https://webpack.js.org/configuration/devtool/)
 * or disable the default devtool with "devtool: false".
 * If you are looking for production-ready output files, see mode: "production" (https://webpack.js.org/configuration/mode/).
 */
/******/ (() => { // webpackBootstrap
/******/ 	var __webpack_modules__ = ({

/***/ "./assets/js/src/admin.js":
/*!********************************!*\
  !*** ./assets/js/src/admin.js ***!
  \********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
eval("__webpack_require__.r(__webpack_exports__);\n/* harmony import */ var _event_sidebar__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./event-sidebar */ \"./assets/js/src/event-sidebar.js\");\n/* harmony import */ var _event_sidebar__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_event_sidebar__WEBPACK_IMPORTED_MODULE_0__);\n// Admin entry point\n\n\n//# sourceURL=webpack://mayo/./assets/js/src/admin.js?");

/***/ }),

/***/ "./assets/js/src/event-sidebar.js":
/*!****************************************!*\
  !*** ./assets/js/src/event-sidebar.js ***!
  \****************************************/
/***/ (() => {

eval("const {\n  registerPlugin\n} = wp.plugins;\nconst {\n  useSelect,\n  useDispatch\n} = wp.data;\nconst {\n  __\n} = wp.i18n;\nconst {\n  TextControl,\n  Button,\n  PanelBody\n} = wp.components;\nconst {\n  PluginDocumentSettingPanel\n} = wp.editPost;\nconst {\n  MediaUpload,\n  MediaUploadCheck\n} = wp.blockEditor;\nconst EventDetailsSidebar = () => {\n  const postType = useSelect(select => select('core/editor').getCurrentPostType());\n  if (postType !== 'mayo_event') return null;\n  const meta = useSelect(select => {\n    const currentMeta = select('core/editor').getEditedPostAttribute('meta') || {};\n    return {\n      event_type: currentMeta.event_type || '',\n      event_date: currentMeta.event_date || '',\n      event_start_time: currentMeta.event_start_time || '',\n      event_end_time: currentMeta.event_end_time || '',\n      flyer_id: currentMeta.flyer_id || '',\n      flyer_url: currentMeta.flyer_url || '',\n      recurring_schedule: currentMeta.recurring_schedule || '',\n      location_name: currentMeta.location_name || '',\n      location_address: currentMeta.location_address || '',\n      location_details: currentMeta.location_details || ''\n    };\n  });\n  const {\n    editPost\n  } = useDispatch('core/editor');\n  const updateMetaValue = (key, value) => {\n    editPost({\n      meta: {\n        ...meta,\n        [key]: value\n      }\n    });\n  };\n  const onSelectImage = media => {\n    updateMetaValue('flyer_id', media.id);\n    updateMetaValue('flyer_url', media.url);\n  };\n  const removeImage = () => {\n    updateMetaValue('flyer_id', '');\n    updateMetaValue('flyer_url', '');\n  };\n  return /*#__PURE__*/React.createElement(PluginDocumentSettingPanel, {\n    name: \"event-details\",\n    title: __('Event Details'),\n    className: \"mayo-event-details\",\n    initialOpen: true\n  }, /*#__PURE__*/React.createElement(\"div\", {\n    style: {\n      padding: '8px 0'\n    }\n  }, /*#__PURE__*/React.createElement(TextControl, {\n    label: __('Event Type'),\n    value: meta.event_type,\n    onChange: value => updateMetaValue('event_type', value),\n    __nextHasNoMarginBottom: true\n  }), /*#__PURE__*/React.createElement(TextControl, {\n    label: __('Event Date'),\n    type: \"date\",\n    value: meta.event_date,\n    onChange: value => updateMetaValue('event_date', value),\n    __nextHasNoMarginBottom: true\n  }), /*#__PURE__*/React.createElement(TextControl, {\n    label: __('Start Time'),\n    type: \"time\",\n    value: meta.event_start_time,\n    onChange: value => updateMetaValue('event_start_time', value),\n    __nextHasNoMarginBottom: true\n  }), /*#__PURE__*/React.createElement(TextControl, {\n    label: __('End Time'),\n    type: \"time\",\n    value: meta.event_end_time,\n    onChange: value => updateMetaValue('event_end_time', value),\n    __nextHasNoMarginBottom: true\n  }), /*#__PURE__*/React.createElement(TextControl, {\n    label: __('Recurring Schedule'),\n    value: meta.recurring_schedule,\n    onChange: value => updateMetaValue('recurring_schedule', value),\n    __nextHasNoMarginBottom: true\n  }), /*#__PURE__*/React.createElement(\"div\", {\n    className: \"editor-post-featured-image\"\n  }, /*#__PURE__*/React.createElement(MediaUploadCheck, null, /*#__PURE__*/React.createElement(MediaUpload, {\n    onSelect: onSelectImage,\n    allowedTypes: ['image'],\n    value: meta.flyer_id,\n    render: ({\n      open\n    }) => /*#__PURE__*/React.createElement(\"div\", null, meta.flyer_url ? /*#__PURE__*/React.createElement(\"div\", null, /*#__PURE__*/React.createElement(\"img\", {\n      src: meta.flyer_url,\n      alt: __('Event Flyer'),\n      style: {\n        maxWidth: '100%',\n        marginBottom: '8px'\n      }\n    }), /*#__PURE__*/React.createElement(\"div\", null, /*#__PURE__*/React.createElement(Button, {\n      onClick: open,\n      isSecondary: true,\n      style: {\n        marginRight: '8px'\n      }\n    }, __('Replace Flyer')), /*#__PURE__*/React.createElement(Button, {\n      onClick: removeImage,\n      isDestructive: true\n    }, __('Remove Flyer')))) : /*#__PURE__*/React.createElement(Button, {\n      onClick: open,\n      isPrimary: true\n    }, __('Upload Event Flyer')))\n  }))), /*#__PURE__*/React.createElement(PanelBody, {\n    title: __('Location Details'),\n    initialOpen: true\n  }, /*#__PURE__*/React.createElement(TextControl, {\n    label: __('Location Name'),\n    value: meta.location_name,\n    onChange: value => updateMetaValue('location_name', value),\n    placeholder: __('e.g., Community Center'),\n    __nextHasNoMarginBottom: true\n  }), /*#__PURE__*/React.createElement(TextControl, {\n    label: __('Address'),\n    value: meta.location_address,\n    onChange: value => updateMetaValue('location_address', value),\n    placeholder: __('Full address'),\n    __nextHasNoMarginBottom: true\n  }), /*#__PURE__*/React.createElement(TextControl, {\n    label: __('Location Details'),\n    value: meta.location_details,\n    onChange: value => updateMetaValue('location_details', value),\n    placeholder: __('Parking info, entrance details, etc.'),\n    __nextHasNoMarginBottom: true\n  }))));\n};\nregisterPlugin('mayo-event-details', {\n  render: EventDetailsSidebar\n});\n\n//# sourceURL=webpack://mayo/./assets/js/src/event-sidebar.js?");

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
/******/ 	
/******/ 	// startup
/******/ 	// Load entry module and return exports
/******/ 	// This entry module can't be inlined because the eval devtool is used.
/******/ 	var __webpack_exports__ = __webpack_require__("./assets/js/src/admin.js");
/******/ 	
/******/ })()
;