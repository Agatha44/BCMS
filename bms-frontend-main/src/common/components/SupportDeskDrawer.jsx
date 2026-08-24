import { Button, Col, Drawer, Form, Input, Row, Select, Space, Upload } from 'antd';
import { CheckCircleIcon } from '@heroicons/react/20/solid/index.js';
import { UploadOutlined } from '@ant-design/icons';
import { useState } from 'react';
import PropTypes from 'prop-types';

export default function SupportDeskDrawer({ onClose, open }) {
  const [fileList, setFileList] = useState([]);
  const props = {
    onRemove: (file) => {
      const index = fileList.indexOf(file);
      const newFileList = fileList.slice();
      newFileList.splice(index, 1);
      setFileList(newFileList);
    },
    beforeUpload: (file) => {
      setFileList([...fileList, file]);
      return false;
    },
    fileList
  };
  return (
    <>
      <Drawer
        title="Support Desk"
        width={typeof window !== 'undefined' && window.innerWidth < 768 ? '100%' : 600}
        onClose={onClose}
        open={open}
        styles={{
          body: {
            paddingBottom: 80
          }
        }}
      >
        <Form layout="vertical">
          <Row gutter={16}>
            <Col span={12}>
              <Form.Item
                name="source"
                label="Source"
                rules={[
                  {
                    required: true,
                    message: 'Please select a module'
                  }
                ]}
              >
                <Select placeholder="Please select a module">
                  <Select.Option value="xiao">Imprest Module</Select.Option>
                  <Select.Option value="mao">Payment Module</Select.Option>
                </Select>
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item
                name="subject"
                label="Subject"
                rules={[
                  {
                    required: true,
                    message: 'Please enter subject'
                  }
                ]}
              >
                <Input placeholder="Please enter subject" />
              </Form.Item>
            </Col>
          </Row>
          <Row gutter={16}>
            <Col span={24}>
              <Form.Item
                name="description"
                label="Description"
                rules={[
                  {
                    required: true,
                    message: 'please enter url description'
                  }
                ]}
              >
                <Input.TextArea rows={4} placeholder="please enter url description" />
              </Form.Item>
            </Col>
          </Row>
          <Row gutter={16}>
            <Col span={24}>
              <Form.Item
                label="Attachment"
                name="attachment"
                rules={[
                  {
                    required: true,
                    message: 'Please upload document'
                  }
                ]}
              >
                <Upload {...props}>
                  <Button icon={<UploadOutlined />}>Select File</Button>
                </Upload>{' '}
              </Form.Item>
            </Col>
          </Row>
          <Row gutter={16}>
            <Form.Item>
              <Space>
                <button
                  onClick={onClose}
                  type="button"
                  className="inline-flex items-center gap-x-1.5 rounded-md bg-primary
                                 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-red-900
                                 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2
                                 focus-visible:outline-red-800"
                >
                  <CheckCircleIcon className="-ml-0.5 h-5 w-5" aria-hidden="true" />
                  Submit
                </button>
                <button
                  type="button"
                  onClick={onClose}
                  className="rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                >
                  Cancel
                </button>
              </Space>
            </Form.Item>
          </Row>
        </Form>
      </Drawer>
    </>
  );
}

SupportDeskDrawer.propTypes = {
  onClose: PropTypes.func,
  open: PropTypes.bool
};
