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

/***/ "./assets/js/src/components/EventCalendar.js":
/*!***************************************************!*\
  !*** ./assets/js/src/components/EventCalendar.js ***!
  \***************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

eval("__webpack_require__.r(__webpack_exports__);\n/* harmony export */ __webpack_require__.d(__webpack_exports__, {\n/* harmony export */   \"default\": () => (__WEBPACK_DEFAULT_EXPORT__)\n/* harmony export */ });\n/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ \"@wordpress/element\");\n/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);\n\nconst EventCalendar = ({\n  events\n}) => {\n  const [currentDate, setCurrentDate] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(new Date());\n\n  // Get calendar data for current month\n  const getDaysInMonth = date => {\n    const year = date.getFullYear();\n    const month = date.getMonth();\n    const daysInMonth = new Date(year, month + 1, 0).getDate();\n    const firstDayOfMonth = new Date(year, month, 1).getDay();\n    const days = [];\n    // Add empty cells for days before the first of the month\n    for (let i = 0; i < firstDayOfMonth; i++) {\n      days.push(null);\n    }\n\n    // Add days of the month\n    for (let i = 1; i <= daysInMonth; i++) {\n      days.push(new Date(year, month, i));\n    }\n    return days;\n  };\n  const getEventsForDate = date => {\n    if (!date) return [];\n    return events.filter(event => {\n      const eventDate = new Date(event.meta.event_start_date);\n      return eventDate.toDateString() === date.toDateString();\n    });\n  };\n  const days = getDaysInMonth(currentDate);\n  const monthNames = [\"January\", \"February\", \"March\", \"April\", \"May\", \"June\", \"July\", \"August\", \"September\", \"October\", \"November\", \"December\"];\n  const changeMonth = increment => {\n    setCurrentDate(prev => {\n      const newDate = new Date(prev);\n      newDate.setMonth(prev.getMonth() + increment);\n      return newDate;\n    });\n  };\n  const handleEventClick = (event, e) => {\n    e.stopPropagation(); // Prevent day click when clicking event\n    window.location.href = event.link;\n  };\n  return /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-calendar\"\n  }, /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-calendar-header\"\n  }, /*#__PURE__*/React.createElement(\"button\", {\n    onClick: () => changeMonth(-1)\n  }, /*#__PURE__*/React.createElement(\"span\", {\n    className: \"dashicons dashicons-arrow-left-alt2\"\n  })), /*#__PURE__*/React.createElement(\"h2\", null, monthNames[currentDate.getMonth()], \" \", currentDate.getFullYear()), /*#__PURE__*/React.createElement(\"button\", {\n    onClick: () => changeMonth(1)\n  }, /*#__PURE__*/React.createElement(\"span\", {\n    className: \"dashicons dashicons-arrow-right-alt2\"\n  }))), /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-calendar-grid\"\n  }, /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-calendar-weekdays\"\n  }, ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map(day => /*#__PURE__*/React.createElement(\"div\", {\n    key: day,\n    className: \"mayo-calendar-weekday\"\n  }, day))), /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-calendar-days\"\n  }, days.map((date, index) => {\n    const dayEvents = getEventsForDate(date);\n    return /*#__PURE__*/React.createElement(\"div\", {\n      key: index,\n      className: `mayo-calendar-day ${!date ? 'empty' : ''}`\n    }, date && /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement(\"span\", {\n      className: \"mayo-calendar-date\"\n    }, date.getDate()), dayEvents.length > 0 && /*#__PURE__*/React.createElement(\"div\", {\n      className: \"mayo-calendar-events\"\n    }, dayEvents.map(event => /*#__PURE__*/React.createElement(\"div\", {\n      key: event.id,\n      className: \"mayo-calendar-event\",\n      onClick: e => handleEventClick(event, e),\n      title: `View details for ${event.title.rendered}`\n    }, /*#__PURE__*/React.createElement(\"span\", {\n      className: \"event-time\"\n    }, event.meta.event_start_time), /*#__PURE__*/React.createElement(\"span\", {\n      className: \"event-title\"\n    }, event.title.rendered))))));\n  }))));\n};\n/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (EventCalendar);\n\n//# sourceURL=webpack://mayo/./assets/js/src/components/EventCalendar.js?");

/***/ }),

/***/ "./assets/js/src/components/EventForm.js":
/*!***********************************************!*\
  !*** ./assets/js/src/components/EventForm.js ***!
  \***********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

eval("__webpack_require__.r(__webpack_exports__);\n/* harmony export */ __webpack_require__.d(__webpack_exports__, {\n/* harmony export */   \"default\": () => (__WEBPACK_DEFAULT_EXPORT__)\n/* harmony export */ });\n/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ \"@wordpress/element\");\n/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);\n\nconst EventForm = () => {\n  const [formData, setFormData] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)({\n    event_name: '',\n    event_type: '',\n    event_start_date: '',\n    event_end_date: '',\n    event_start_time: '',\n    event_end_time: '',\n    description: '',\n    flyer: null,\n    location_name: '',\n    location_address: '',\n    location_details: '',\n    categories: [],\n    tags: []\n  });\n  const [isSubmitting, setIsSubmitting] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);\n  const [message, setMessage] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);\n  const [categories, setCategories] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);\n  const [tags, setTags] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);\n  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {\n    // Fetch available categories and tags\n    const fetchTaxonomies = async () => {\n      try {\n        const [categoriesRes, tagsRes] = await Promise.all([fetch('/wp-json/wp/v2/mayo_event_category'), fetch('/wp-json/wp/v2/mayo_event_tag')]);\n        const categoriesData = await categoriesRes.json();\n        const tagsData = await tagsRes.json();\n        setCategories(categoriesData);\n        setTags(tagsData);\n      } catch (error) {\n        console.error('Error fetching taxonomies:', error);\n      }\n    };\n    fetchTaxonomies();\n  }, []);\n  const handleSubmit = async e => {\n    e.preventDefault();\n    setIsSubmitting(true);\n    setMessage(null);\n    const data = new FormData();\n    Object.keys(formData).forEach(key => {\n      data.append(key, formData[key]);\n    });\n    try {\n      const response = await fetch('/wp-json/event-manager/v1/submit-event', {\n        method: 'POST',\n        body: data\n      });\n      const result = await response.json();\n      if (result.success) {\n        setMessage({\n          type: 'success',\n          text: 'Event submitted successfully! Awaiting approval.'\n        });\n        setFormData({\n          event_name: '',\n          event_type: '',\n          event_start_date: '',\n          event_end_date: '',\n          event_start_time: '',\n          event_end_time: '',\n          description: '',\n          flyer: null,\n          location_name: '',\n          location_address: '',\n          location_details: '',\n          categories: [],\n          tags: []\n        });\n      } else {\n        setMessage({\n          type: 'error',\n          text: result.message\n        });\n      }\n    } catch (error) {\n      setMessage({\n        type: 'error',\n        text: 'Error submitting event. Please try again.'\n      });\n    } finally {\n      setIsSubmitting(false);\n    }\n  };\n  const handleChange = e => {\n    const {\n      name,\n      value,\n      files\n    } = e.target;\n    setFormData(prev => ({\n      ...prev,\n      [name]: files ? files[0] : value\n    }));\n  };\n  return /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-event-form\"\n  }, message && /*#__PURE__*/React.createElement(\"div\", {\n    className: `mayo-message mayo-message-${message.type}`\n  }, message.text), /*#__PURE__*/React.createElement(\"form\", {\n    onSubmit: handleSubmit\n  }, /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-form-field\"\n  }, /*#__PURE__*/React.createElement(\"label\", {\n    htmlFor: \"event_name\"\n  }, \"Event Name *\"), /*#__PURE__*/React.createElement(\"input\", {\n    type: \"text\",\n    id: \"event_name\",\n    name: \"event_name\",\n    value: formData.event_name,\n    onChange: handleChange,\n    required: true\n  })), /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-form-field\"\n  }, /*#__PURE__*/React.createElement(\"label\", {\n    htmlFor: \"event_type\"\n  }, \"Event Type *\"), /*#__PURE__*/React.createElement(\"select\", {\n    id: \"event_type\",\n    name: \"event_type\",\n    value: formData.event_type,\n    onChange: handleChange,\n    required: true\n  }, /*#__PURE__*/React.createElement(\"option\", {\n    value: \"\"\n  }, \"Select Event Type\"), /*#__PURE__*/React.createElement(\"option\", {\n    value: \"Service\"\n  }, \"Service\"), /*#__PURE__*/React.createElement(\"option\", {\n    value: \"Activity\"\n  }, \"Activity\"))), /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-datetime-group\"\n  }, /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-form-field\"\n  }, /*#__PURE__*/React.createElement(\"label\", null, \"Start Date/Time *\"), /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-datetime-inputs\"\n  }, /*#__PURE__*/React.createElement(\"input\", {\n    type: \"date\",\n    id: \"event_start_date\",\n    name: \"event_start_date\",\n    value: formData.event_start_date,\n    onChange: handleChange,\n    required: true\n  }), /*#__PURE__*/React.createElement(\"input\", {\n    type: \"time\",\n    id: \"event_start_time\",\n    name: \"event_start_time\",\n    value: formData.event_start_time,\n    onChange: handleChange,\n    required: true\n  }))), /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-form-field\"\n  }, /*#__PURE__*/React.createElement(\"label\", null, \"End Date/Time *\"), /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-datetime-inputs\"\n  }, /*#__PURE__*/React.createElement(\"input\", {\n    type: \"date\",\n    id: \"event_end_date\",\n    name: \"event_end_date\",\n    value: formData.event_end_date,\n    onChange: handleChange\n  }), /*#__PURE__*/React.createElement(\"input\", {\n    type: \"time\",\n    id: \"event_end_time\",\n    name: \"event_end_time\",\n    value: formData.event_end_time,\n    onChange: handleChange,\n    required: true\n  })))), /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-form-field\"\n  }, /*#__PURE__*/React.createElement(\"label\", {\n    htmlFor: \"description\"\n  }, \"Description\"), /*#__PURE__*/React.createElement(\"textarea\", {\n    id: \"description\",\n    name: \"description\",\n    value: formData.description,\n    onChange: handleChange\n  })), /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-form-field\"\n  }, /*#__PURE__*/React.createElement(\"label\", {\n    htmlFor: \"flyer\"\n  }, \"Event Flyer\"), /*#__PURE__*/React.createElement(\"input\", {\n    type: \"file\",\n    id: \"flyer\",\n    name: \"flyer\",\n    accept: \"image/*\",\n    onChange: handleChange\n  })), /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-form-field\"\n  }, /*#__PURE__*/React.createElement(\"label\", {\n    htmlFor: \"location_name\"\n  }, \"Location Name\"), /*#__PURE__*/React.createElement(\"input\", {\n    type: \"text\",\n    id: \"location_name\",\n    name: \"location_name\",\n    value: formData.location_name,\n    onChange: handleChange\n  })), /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-form-field\"\n  }, /*#__PURE__*/React.createElement(\"label\", {\n    htmlFor: \"location_address\"\n  }, \"Address\"), /*#__PURE__*/React.createElement(\"input\", {\n    type: \"text\",\n    id: \"location_address\",\n    name: \"location_address\",\n    value: formData.location_address,\n    onChange: handleChange\n  })), /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-form-field\"\n  }, /*#__PURE__*/React.createElement(\"label\", {\n    htmlFor: \"location_details\"\n  }, \"Location Details\"), /*#__PURE__*/React.createElement(\"textarea\", {\n    id: \"location_details\",\n    name: \"location_details\",\n    value: formData.location_details,\n    onChange: handleChange,\n    placeholder: \"Additional details about the location (e.g., parking, entrance info)\"\n  })), /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-form-field\"\n  }, /*#__PURE__*/React.createElement(\"label\", null, \"Categories\"), /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-taxonomy-list\"\n  }, categories.map(category => /*#__PURE__*/React.createElement(\"label\", {\n    key: category.id,\n    className: \"mayo-taxonomy-item\"\n  }, /*#__PURE__*/React.createElement(\"input\", {\n    type: \"checkbox\",\n    checked: formData.categories.includes(category.id),\n    onChange: e => {\n      const newCategories = e.target.checked ? [...formData.categories, category.id] : formData.categories.filter(id => id !== category.id);\n      setFormData({\n        ...formData,\n        categories: newCategories\n      });\n    }\n  }), category.name)))), /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-form-field\"\n  }, /*#__PURE__*/React.createElement(\"label\", null, \"Tags\"), /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-taxonomy-list\"\n  }, tags.map(tag => /*#__PURE__*/React.createElement(\"label\", {\n    key: tag.id,\n    className: \"mayo-taxonomy-item\"\n  }, /*#__PURE__*/React.createElement(\"input\", {\n    type: \"checkbox\",\n    checked: formData.tags.includes(tag.id),\n    onChange: e => {\n      const newTags = e.target.checked ? [...formData.tags, tag.id] : formData.tags.filter(id => id !== tag.id);\n      setFormData({\n        ...formData,\n        tags: newTags\n      });\n    }\n  }), tag.name)))), /*#__PURE__*/React.createElement(\"button\", {\n    type: \"submit\",\n    disabled: isSubmitting,\n    className: \"mayo-submit-button\"\n  }, isSubmitting ? 'Submitting...' : 'Submit Event')));\n};\n/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (EventForm);\n\n//# sourceURL=webpack://mayo/./assets/js/src/components/EventForm.js?");

/***/ }),

/***/ "./assets/js/src/components/EventList.js":
/*!***********************************************!*\
  !*** ./assets/js/src/components/EventList.js ***!
  \***********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

eval("__webpack_require__.r(__webpack_exports__);\n/* harmony export */ __webpack_require__.d(__webpack_exports__, {\n/* harmony export */   \"default\": () => (__WEBPACK_DEFAULT_EXPORT__)\n/* harmony export */ });\n/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ \"@wordpress/element\");\n/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);\n/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/components */ \"@wordpress/components\");\n/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__);\n/* harmony import */ var _EventCalendar__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./EventCalendar */ \"./assets/js/src/components/EventCalendar.js\");\n\n\n\nconst formatTime = (time, format) => {\n  if (!time) return '';\n  if (format === '24hour') {\n    return time;\n  }\n\n  // Convert to 12-hour format\n  const [hours, minutes] = time.split(':');\n  const hour = parseInt(hours);\n  const ampm = hour >= 12 ? 'PM' : 'AM';\n  const hour12 = hour % 12 || 12;\n  return `${hour12}:${minutes} ${ampm}`;\n};\nconst EventCard = ({\n  event,\n  timeFormat\n}) => {\n  const [isExpanded, setIsExpanded] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);\n  return /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-event-card\"\n  }, /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-event-header\",\n    onClick: () => setIsExpanded(!isExpanded)\n  }, /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-event-summary\"\n  }, /*#__PURE__*/React.createElement(\"h3\", null, event.title.rendered), /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-event-brief\"\n  }, /*#__PURE__*/React.createElement(\"span\", {\n    className: \"mayo-event-type\"\n  }, event.meta.event_type), /*#__PURE__*/React.createElement(\"span\", {\n    className: \"mayo-event-datetime\"\n  }, new Date(event.meta.event_start_date).toLocaleDateString(), \" | \", ' ', formatTime(event.meta.event_start_time, timeFormat), \" - \", formatTime(event.meta.event_end_time, timeFormat)))), /*#__PURE__*/React.createElement(\"span\", {\n    className: `mayo-caret dashicons ${isExpanded ? 'dashicons-arrow-up-alt2' : 'dashicons-arrow-down-alt2'}`\n  })), isExpanded && /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-event-details\"\n  }, event.meta.flyer_url && /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-event-image\"\n  }, /*#__PURE__*/React.createElement(\"img\", {\n    src: event.meta.flyer_url,\n    alt: event.title.rendered\n  })), /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-event-content\"\n  }, /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-event-description\",\n    dangerouslySetInnerHTML: {\n      __html: event.content.rendered\n    }\n  }), event.meta.recurring_schedule && /*#__PURE__*/React.createElement(\"p\", {\n    className: \"mayo-event-recurring\"\n  }, \"Recurring: \", event.meta.recurring_schedule), (event.meta.location_name || event.meta.location_address) && /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-event-location\"\n  }, /*#__PURE__*/React.createElement(\"h4\", null, \"Location\"), event.meta.location_name && /*#__PURE__*/React.createElement(\"p\", {\n    className: \"mayo-location-name\"\n  }, event.meta.location_name), event.meta.location_address && /*#__PURE__*/React.createElement(\"p\", {\n    className: \"mayo-location-address\"\n  }, /*#__PURE__*/React.createElement(\"a\", {\n    href: `https://maps.google.com?q=${encodeURIComponent(event.meta.location_address)}`,\n    target: \"_blank\",\n    rel: \"noopener noreferrer\",\n    onClick: e => e.stopPropagation()\n  }, event.meta.location_address)), event.meta.location_details && /*#__PURE__*/React.createElement(\"p\", {\n    className: \"mayo-location-details\"\n  }, event.meta.location_details)), /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-event-actions\"\n  }, /*#__PURE__*/React.createElement(\"a\", {\n    href: event.link,\n    className: \"mayo-read-more\",\n    onClick: e => e.stopPropagation()\n  }, \"Read More\")))));\n};\nconst EventList = () => {\n  const [events, setEvents] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);\n  const [loading, setLoading] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(true);\n  const [error, setError] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);\n  const [view, setView] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('list'); // 'list' or 'calendar'\n  const containerRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useRef)(null);\n  const [timeFormat, setTimeFormat] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('12hour');\n  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {\n    // Get the container element and read the time format\n    const container = document.getElementById('mayo-event-list');\n    if (container) {\n      const format = container.dataset.timeFormat || '12hour';\n      setTimeFormat(format);\n    }\n    fetchEvents();\n  }, []);\n  const fetchEvents = async () => {\n    try {\n      const response = await fetch('/wp-json/event-manager/v1/events');\n      const data = await response.json();\n      const now = new Date();\n      const upcomingEvents = data.filter(event => {\n        const eventDate = new Date(`${event.meta.event_start_date} ${event.meta.event_start_time}`);\n        return eventDate > now;\n      }).sort((a, b) => {\n        const dateA = new Date(`${a.meta.event_start_date} ${a.meta.event_start_time}`);\n        const dateB = new Date(`${b.meta.event_start_date} ${b.meta.event_start_time}`);\n        return dateA - dateB;\n      });\n      setEvents(upcomingEvents);\n      setLoading(false);\n    } catch (err) {\n      setError('Failed to load events');\n      setLoading(false);\n    }\n  };\n  if (loading) return /*#__PURE__*/React.createElement(\"div\", null, \"Loading events...\");\n  if (error) return /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-error\"\n  }, error);\n  if (!events.length) return /*#__PURE__*/React.createElement(\"div\", null, \"No upcoming events\");\n  return /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-event-list\",\n    ref: containerRef\n  }, /*#__PURE__*/React.createElement(\"div\", {\n    className: \"mayo-view-switcher\"\n  }, /*#__PURE__*/React.createElement(\"button\", {\n    className: `mayo-view-button ${view === 'list' ? 'active' : ''}`,\n    onClick: () => setView('list')\n  }, \"List View\"), /*#__PURE__*/React.createElement(\"button\", {\n    className: `mayo-view-button ${view === 'calendar' ? 'active' : ''}`,\n    onClick: () => setView('calendar')\n  }, \"Calendar View\")), view === 'list' ? events.map(event => /*#__PURE__*/React.createElement(EventCard, {\n    key: event.id,\n    event: event,\n    timeFormat: timeFormat\n  })) : /*#__PURE__*/React.createElement(_EventCalendar__WEBPACK_IMPORTED_MODULE_2__[\"default\"], {\n    events: events\n  }));\n};\n/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (EventList);\n\n//# sourceURL=webpack://mayo/./assets/js/src/components/EventList.js?");

/***/ }),

/***/ "./assets/js/src/public.js":
/*!*********************************!*\
  !*** ./assets/js/src/public.js ***!
  \*********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

eval("__webpack_require__.r(__webpack_exports__);\n/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ \"@wordpress/element\");\n/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);\n/* harmony import */ var _components_EventForm__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./components/EventForm */ \"./assets/js/src/components/EventForm.js\");\n/* harmony import */ var _components_EventList__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./components/EventList */ \"./assets/js/src/components/EventList.js\");\n// Public entry point\n// Add any public-facing JavaScript here \n\n\n\n\ndocument.addEventListener('DOMContentLoaded', () => {\n  const formContainer = document.getElementById('mayo-event-form');\n  if (formContainer) {\n    (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.render)(/*#__PURE__*/React.createElement(_components_EventForm__WEBPACK_IMPORTED_MODULE_1__[\"default\"], null), formContainer);\n  }\n  const listContainer = document.getElementById('mayo-event-list');\n  if (listContainer) {\n    (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.render)(/*#__PURE__*/React.createElement(_components_EventList__WEBPACK_IMPORTED_MODULE_2__[\"default\"], null), listContainer);\n  }\n});\n\n//# sourceURL=webpack://mayo/./assets/js/src/public.js?");

/***/ }),

/***/ "@wordpress/components":
/*!********************************!*\
  !*** external "wp.components" ***!
  \********************************/
/***/ ((module) => {

module.exports = wp.components;

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