// React hook shortcuts, shared globally across every component file below.
// (Classic <script> tags on one page share a single top-level scope, so this
// only needs to run once, before anything else that uses useState/useEffect/etc.)
const { useState, useEffect, useRef, useCallback } = React;
