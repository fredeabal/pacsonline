try {
  localStorage.setItem('i18nextLng', 'es');
} catch (e) {}

window.config = {
  routerBasename: '/viewer',
  showStudyList: false,
  investigationalUseDialog: {
    option: 'never',
  },
  i18n: {
    lng: 'es',
  },
  customizationService: {
    'ohif.tours': {
      $set: [],
    },
  },
  whiteLabeling: {
    createLogoComponentFn: function (React) {
      return React.createElement(
        'a',
        {
          href: '/',
          style: {
            display: 'inline-flex',
            alignItems: 'center',
            gap: '8px',
            textDecoration: 'none',
            color: '#ffffff',
            fontWeight: 700,
            fontSize: '16px',
            letterSpacing: '-0.01em',
          },
        },
        React.createElement(
          'span',
          {
            style: {
              display: 'inline-flex',
              alignItems: 'center',
              justifyContent: 'center',
              width: '28px',
              height: '28px',
              borderRadius: '6px',
              backgroundColor: '#5d87ff',
              color: '#ffffff',
            },
          },
          React.createElement(
            'svg',
            {
              width: 18,
              height: 18,
              viewBox: '0 0 24 24',
              fill: 'none',
              stroke: 'currentColor',
              strokeWidth: 2,
              strokeLinecap: 'round',
              strokeLinejoin: 'round',
            },
            React.createElement('path', { d: 'M4 7v-1a2 2 0 0 1 2 -2h2' }),
            React.createElement('path', { d: 'M4 17v1a2 2 0 0 0 2 2h2' }),
            React.createElement('path', { d: 'M16 4h2a2 2 0 0 1 2 2v1' }),
            React.createElement('path', { d: 'M16 20h2a2 2 0 0 0 2 -2v-1' }),
            React.createElement('path', { d: 'M5 12l14 0' })
          )
        ),
        React.createElement(
          'span',
          null,
          'PACS ',
          React.createElement('span', { style: { color: '#5d87ff' } }, 'Online')
        )
      );
    },
  },
  extensions: [],
  modes: [],
  dataSources: [
    {
      namespace: '@ohif/extension-default.dataSourcesModule.dicomweb',
      sourceName: 'dicomweb',
      configuration: {
        friendlyName: 'PACS Online Orthanc',
        name: 'orthanc',
        wadoUriRoot: '/dicom-web',
        qidoRoot: '/dicom-web',
        wadoRoot: '/dicom-web',
        qidoSupportsIncludeField: false,
        supportsReject: false,
        imageRendering: 'wadors',
        thumbnailRendering: 'wadors',
        enableStudyLazyLoad: true,
        supportsFuzzyMatching: false,
        supportsWildcard: true,
        omitQuotationForMultipartRequest: true,
      },
    },
  ],
  defaultDataSourceName: 'dicomweb',
};
