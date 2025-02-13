/*
 * ATTENTION: The "eval" devtool has been used (maybe by default in mode: "development").
 * This devtool is neither made for production nor for readable output files.
 * It uses "eval()" calls to create a separate source file in the browser devtools.
 * If you are trying to read the output file, select a different devtool (https://webpack.js.org/configuration/devtool/)
 * or disable the default devtool with "devtool: false".
 * If you are looking for production-ready output files, see mode: "production" (https://webpack.js.org/configuration/mode/).
 */
/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./assets/js/src/components/EventForm.js":
/*!***********************************************!*\
  !*** ./assets/js/src/components/EventForm.js ***!
  \***********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

eval("__webpack_require__.r(__webpack_exports__);\n/* harmony export */ __webpack_require__.d(__webpack_exports__, {\n/* harmony export */   \"default\": () => (__WEBPACK_DEFAULT_EXPORT__)\n/* harmony export */ });\n/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ \"@wordpress/element\");\n/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);\n\nconst EventForm = () => {\n  const [formData, setFormData] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)({\n    event_name: '',\n    event_type: '',\n    event_date: '',\n    event_start_time: '',\n    event_end_time: '',\n    description: '',\n    flyer: null\n  });\n  const [isSubmitting, setIsSubmitting] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);\n  const [message, setMessage] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);\n  const handleSubmit = async e => {\n    e.preventDefault();\n    setIsSubmitting(true);\n    setMessage(null);\n    const data = new FormData();\n    Object.keys(formData).forEach(key => {\n      data.append(key, formData[key]);\n    });\n    try {\n      const response = await fetch('/wp-json/event-manager/v1/submit-event', {\n        method: 'POST',\n        body: data\n      });\n      const result = await response.json();\n      if (result.success) {\n        setMessage({\n          type: 'success',\n          text: 'Event submitted successfully! Awaiting approval.'\n        });\n        setFormData({\n          event_name: '',\n          event_type: '',\n          event_date: '',\n          event_start_time: '',\n          event_end_time: '',\n          description: '',\n          flyer: null\n        });\n      } else {\n        setMessage({\n          type: 'error',\n          text: result.message\n        });\n      }\n    } catch (error) {\n      setMessage({\n        type: 'error',\n        text: 'Error submitting event. Please try again.'\n      });\n    } finally {\n      setIsSubmitting(false);\n    }\n  };\n  const handleChange = e => {\n    const {\n      name,\n      value,\n      files\n    } = e.target;\n    setFormData(prev => ({\n      ...prev,\n      [name]: files ? files[0] : value\n    }));\n  };\n  return /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-event-form\"\n  }, message && /*#__PURE__*/React.createElement(\"div\", {\n    className: `mayo-message mayo-message-${message.type}`\n  }, message.text), /*#__PURE__*/React.createElement(\"form\", {\n    onSubmit: handleSubmit\n  }, /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-form-field\"\n  }, /*#__PURE__*/React.createElement(\"label\", {\n    htmlFor: \"event_name\"\n  }, \"Event Name *\"), /*#__PURE__*/React.createElement(\"input\", {\n    type: \"text\",\n    id: \"event_name\",\n    name: \"event_name\",\n    value: formData.event_name,\n    onChange: handleChange,\n    required: true\n  })), /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-form-field\"\n  }, /*#__PURE__*/React.createElement(\"label\", {\n    htmlFor: \"event_type\"\n  }, \"Event Type *\"), /*#__PURE__*/React.createElement(\"input\", {\n    type: \"text\",\n    id: \"event_type\",\n    name: \"event_type\",\n    value: formData.event_type,\n    onChange: handleChange,\n    required: true\n  })), /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-form-field\"\n  }, /*#__PURE__*/React.createElement(\"label\", {\n    htmlFor: \"event_date\"\n  }, \"Date *\"), /*#__PURE__*/React.createElement(\"input\", {\n    type: \"date\",\n    id: \"event_date\",\n    name: \"event_date\",\n    value: formData.event_date,\n    onChange: handleChange,\n    required: true\n  })), /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-form-field\"\n  }, /*#__PURE__*/React.createElement(\"label\", {\n    htmlFor: \"event_start_time\"\n  }, \"Start Time *\"), /*#__PURE__*/React.createElement(\"input\", {\n    type: \"time\",\n    id: \"event_start_time\",\n    name: \"event_start_time\",\n    value: formData.event_start_time,\n    onChange: handleChange,\n    required: true\n  })), /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-form-field\"\n  }, /*#__PURE__*/React.createElement(\"label\", {\n    htmlFor: \"event_end_time\"\n  }, \"End Time *\"), /*#__PURE__*/React.createElement(\"input\", {\n    type: \"time\",\n    id: \"event_end_time\",\n    name: \"event_end_time\",\n    value: formData.event_end_time,\n    onChange: handleChange,\n    required: true\n  })), /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-form-field\"\n  }, /*#__PURE__*/React.createElement(\"label\", {\n    htmlFor: \"description\"\n  }, \"Description\"), /*#__PURE__*/React.createElement(\"textarea\", {\n    id: \"description\",\n    name: \"description\",\n    value: formData.description,\n    onChange: handleChange\n  })), /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-form-field\"\n  }, /*#__PURE__*/React.createElement(\"label\", {\n    htmlFor: \"flyer\"\n  }, \"Event Flyer\"), /*#__PURE__*/React.createElement(\"input\", {\n    type: \"file\",\n    id: \"flyer\",\n    name: \"flyer\",\n    accept: \"image/*\",\n    onChange: handleChange\n  })), /*#__PURE__*/React.createElement(\"button\", {\n    type: \"submit\",\n    disabled: isSubmitting,\n    className: \"mayo-submit-button\"\n  }, isSubmitting ? 'Submitting...' : 'Submit Event')));\n};\n/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (EventForm);\n\n//# sourceURL=webpack://mayo/./assets/js/src/components/EventForm.js?");

/***/ }),

/***/ "./assets/js/src/components/EventList.js":
/*!***********************************************!*\
  !*** ./assets/js/src/components/EventList.js ***!
  \***********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

eval("__webpack_require__.r(__webpack_exports__);\n/* harmony export */ __webpack_require__.d(__webpack_exports__, {\n/* harmony export */   \"default\": () => (__WEBPACK_DEFAULT_EXPORT__)\n/* harmony export */ });\n/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ \"@wordpress/element\");\n/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);\n\nconst EventList = () => {\n  const [events, setEvents] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);\n  const [loading, setLoading] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(true);\n  const [error, setError] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);\n  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {\n    fetchEvents();\n  }, []);\n  const fetchEvents = async () => {\n    try {\n      const response = await fetch('/wp-json/event-manager/v1/events');\n      const data = await response.json();\n\n      // Filter and sort events\n      const now = new Date();\n      const upcomingEvents = data.filter(event => {\n        const eventDate = new Date(`${event.meta.event_date} ${event.meta.event_start_time}`);\n        return eventDate > now;\n      }).sort((a, b) => {\n        const dateA = new Date(`${a.meta.event_date} ${a.meta.event_start_time}`);\n        const dateB = new Date(`${b.meta.event_date} ${b.meta.event_start_time}`);\n        return dateA - dateB;\n      });\n      setEvents(upcomingEvents);\n      setLoading(false);\n    } catch (err) {\n      setError('Failed to load events');\n      setLoading(false);\n    }\n  };\n  if (loading) return /*#__PURE__*/React.createElement(\"div\", null, \"Loading events...\");\n  if (error) return /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-error\"\n  }, error);\n  if (!events.length) return /*#__PURE__*/React.createElement(\"div\", null, \"No upcoming events\");\n  return /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-event-list\"\n  }, events.map(event => /*#__PURE__*/React.createElement(\"div\", {\n    key: event.id,\n    className: \"mayo-event-card\"\n  }, event.meta.flyer_url && /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-event-image\"\n  }, /*#__PURE__*/React.createElement(\"img\", {\n    src: event.meta.flyer_url,\n    alt: event.title.rendered\n  })), /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-event-content\"\n  }, /*#__PURE__*/React.createElement(\"h3\", null, event.title.rendered), /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-event-details\"\n  }, /*#__PURE__*/React.createElement(\"p\", {\n    className: \"mayo-event-type\"\n  }, event.meta.event_type), /*#__PURE__*/React.createElement(\"p\", {\n    className: \"mayo-event-datetime\"\n  }, new Date(event.meta.event_date).toLocaleDateString(), \" | \", ' ', event.meta.event_start_time, \" - \", event.meta.event_end_time)), /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-event-description\",\n    dangerouslySetInnerHTML: {\n      __html: event.content.rendered\n    }\n  })))));\n};\n/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (EventList);\n\n//# sourceURL=webpack://mayo/./assets/js/src/components/EventList.js?");

/***/ }),

/***/ "./assets/js/src/public.js":
/*!*********************************!*\
  !*** ./assets/js/src/public.js ***!
  \*********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

eval("__webpack_require__.r(__webpack_exports__);\n/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ \"@wordpress/element\");\n/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);\n/* harmony import */ var _components_EventForm__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./components/EventForm */ \"./assets/js/src/components/EventForm.js\");\n/* harmony import */ var _components_EventList__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./components/EventList */ \"./assets/js/src/components/EventList.js\");\n// Public entry point\n// Add any public-facing JavaScript here \n\n\n\n\ndocument.addEventListener('DOMContentLoaded', () => {\n  const formContainer = document.getElementById('mayo-event-form');\n  if (formContainer) {\n    (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.render)(/*#__PURE__*/React.createElement(_components_EventForm__WEBPACK_IMPORTED_MODULE_1__[\"default\"], null), formContainer);\n  }\n  const listContainer = document.getElementById('mayo-event-list');\n  if (listContainer) {\n    (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.render)(/*#__PURE__*/React.createElement(_components_EventList__WEBPACK_IMPORTED_MODULE_2__[\"default\"], null), listContainer);\n  }\n});\n\n//# sourceURL=webpack://mayo/./assets/js/src/public.js?");

/***/ }),

/***/ "@wordpress/element":
/*!*****************************!*\
  !*** external "wp.element" ***!
  \*****************************/
/***/ ((module) => {

module.exports = wp.element;

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
/******/ 	var __webpack_exports__ = __webpack_require__("./assets/js/src/public.js");
/******/ 	
/******/ })()
;